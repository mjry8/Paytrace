<?php
declare(strict_types=1);

/**
 * Two payment gateways with the SAME methods, so the rest of the app never cares which one is in use:
 *   push(array $txn, string $phone, string $scenario): array   start a payment
 *   query(array $txn): array                                   ask "what happened to this payment?"
 * Both return ['state' => SUCCESS|PENDING|FAILED|CANCELLED|TIMEOUT, 'message' => ..., 'request_id' => ..., 'receipt' => ..., 'amount' => ...]
 * Pick one with USE_SIMULATOR in .env (see get_gateway()).
 */

const SCENARIOS = [
    'SUCCESS'            => 'Success',
    'USER_CANCELLED'     => 'Customer cancels on the phone',
    'INSUFFICIENT_FUNDS' => 'Insufficient funds',
    'TIMEOUT'            => 'Gateway times out',
    'LOST_CALLBACK'      => 'Customer charged, app never told (lost callback)',
    'DUPLICATE_CHARGE'   => 'Customer charged twice',
];

// ------------------------------------------------------------------ simulator
class SimulatorGateway
{
    public const NAME = 'SIMULATOR';

    public function push(array $txn, string $phone, string $scenario = 'SUCCESS'): array
    {
        $ref = $txn['reference'];
        $amount = (float) $txn['amount'];
        $requestId = 'SIM-' . strtoupper(bin2hex(random_bytes(4)));
        switch ($scenario) {
            case 'USER_CANCELLED':
                return $this->answer('CANCELLED', 'Request cancelled by user', $requestId);
            case 'INSUFFICIENT_FUNDS':
                return $this->answer('FAILED', 'The balance is insufficient for the transaction', $requestId);
            case 'TIMEOUT':
                return $this->answer('TIMEOUT', 'Gateway did not answer in time', $requestId);
            case 'LOST_CALLBACK':
                $this->charge($ref, $amount);                       // money taken, but we are told nothing
                return $this->answer('PENDING', 'Awaiting confirmation', $requestId);
            case 'DUPLICATE_CHARGE':
                $receipt = $this->charge($ref, $amount);
                $this->charge($ref, $amount);                       // charged a second time
                return $this->answer('SUCCESS', 'Processed successfully', $requestId, $receipt);
            default:
                return $this->answer('SUCCESS', 'Processed successfully', $requestId, $this->charge($ref, $amount));
        }
    }

    /** What this gateway's own books say about a payment (the simulator's stand-in for Safaricom). */
    public function charges_for(string $reference): array
    {
        $st = db()->prepare('SELECT receipt, amount FROM gateway_ledger WHERE reference = ? ORDER BY id');
        $st->execute([$reference]);
        return $st->fetchAll();
    }

    public function query(array $txn): array
    {
        $charges = $this->charges_for($txn['reference']);
        if ($charges) {
            return $this->answer('SUCCESS', 'Processed successfully', $txn['gateway_request_id'] ?? null,
                $charges[0]['receipt'], (float) $charges[0]['amount']);
        }
        return $this->answer('FAILED', 'No charge found at the gateway', $txn['gateway_request_id'] ?? null);
    }

    private function charge(string $reference, float $amount): string
    {
        $receipt = 'SIM' . strtoupper(bin2hex(random_bytes(4)));
        db()->prepare('INSERT INTO gateway_ledger (reference, receipt, amount, charged_at) VALUES (?,?,?,?)')
            ->execute([$reference, $receipt, $amount, now()]);
        return $receipt;
    }

    private function answer(string $state, string $message, ?string $requestId, ?string $receipt = null, ?float $amount = null): array
    {
        return ['state' => $state, 'message' => $message, 'request_id' => $requestId, 'receipt' => $receipt, 'amount' => $amount];
    }
}

// ------------------------------------------------------------------ Safaricom Daraja
class DarajaGateway
{
    public const NAME = 'DARAJA';

    private string $base;
    private ?string $token = null;

    public function __construct(private array $cfg)
    {
        $this->base = rtrim($cfg['base_url'], '/');
    }

    /** Build from .env, and say clearly what is missing instead of failing later. */
    public static function fromEnv(): self
    {
        $need = ['DARAJA_CONSUMER_KEY', 'DARAJA_CONSUMER_SECRET', 'DARAJA_SHORTCODE', 'DARAJA_PASSKEY', 'PUBLIC_BASE_URL', 'MPESA_CALLBACK_TOKEN'];
        $missing = array_filter($need, fn($k) => env($k) === null);
        if ($missing) {
            throw new RuntimeException('USE_SIMULATOR=false but these settings in .env are empty: ' . implode(', ', $missing));
        }
        $live = env('DARAJA_ENV', 'sandbox') === 'production';
        return new self([
            'base_url'   => env('DARAJA_BASE_URL') ?? ($live ? 'https://api.safaricom.co.ke' : 'https://sandbox.safaricom.co.ke'),
            'key'        => env('DARAJA_CONSUMER_KEY'),
            'secret'     => env('DARAJA_CONSUMER_SECRET'),
            'shortcode'  => env('DARAJA_SHORTCODE'),
            'passkey'    => env('DARAJA_PASSKEY'),
            'type'       => env('DARAJA_TRANSACTION_TYPE', 'CustomerPayBillOnline'),
            'party_b'    => env('DARAJA_TILL_NUMBER') ?? env('DARAJA_SHORTCODE'),
            'callback'   => rtrim((string) env('PUBLIC_BASE_URL'), '/') . '/index.php/callback/' . env('MPESA_CALLBACK_TOKEN'),
            'ca_bundle'  => env('CA_BUNDLE'),
        ]);
    }

    public function push(array $txn, string $phone, string $scenario = ''): array
    {
        [$password, $timestamp] = $this->password();
        $reply = $this->call('/mpesa/stkpush/v1/processrequest', [
            'BusinessShortCode' => $this->cfg['shortcode'],
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'TransactionType'   => $this->cfg['type'],
            'Amount'            => (int) ceil((float) $txn['amount']),     // Daraja takes whole shillings
            'PartyA'            => $phone,
            'PartyB'            => $this->cfg['party_b'],
            'PhoneNumber'       => $phone,
            'CallBackURL'       => $this->cfg['callback'],
            'AccountReference'  => substr($txn['reference'], 0, 12),
            'TransactionDesc'   => 'PayGuard',
        ]);
        if (($reply['ResponseCode'] ?? '') === '0') {
            return ['state' => 'PENDING', 'message' => 'Awaiting customer PIN on phone',
                'request_id' => $reply['CheckoutRequestID'] ?? null, 'receipt' => null, 'amount' => null];
        }
        $why = $reply['errorMessage'] ?? $reply['ResponseDescription'] ?? 'Safaricom rejected the request';
        return ['state' => 'FAILED', 'message' => (string) $why, 'request_id' => null, 'receipt' => null, 'amount' => null];
    }

    public function query(array $txn): array
    {
        [$password, $timestamp] = $this->password();
        $reply = $this->call('/mpesa/stkpushquery/v1/query', [
            'BusinessShortCode' => $this->cfg['shortcode'],
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'CheckoutRequestID' => $txn['gateway_request_id'],
        ], true);
        if (isset($reply['errorCode'])) {                       // 500.001.1001 = still being processed
            return ['state' => 'PENDING', 'message' => (string) ($reply['errorMessage'] ?? 'Still processing'),
                'request_id' => $txn['gateway_request_id'], 'receipt' => null, 'amount' => null];
        }
        $code = $reply['ResultCode'] ?? null;
        return ['state' => self::stateFor($code === null ? null : (int) $code),
            'message' => (string) ($reply['ResultDesc'] ?? ''), 'request_id' => $txn['gateway_request_id'],
            'receipt' => null, 'amount' => null];               // the query never returns a receipt number
    }

    /** Turn Daraja's ResultCode into our status words. 0 = paid, 1032 = cancelled, 1037 = timed out. */
    public static function stateFor(?int $resultCode): string
    {
        return match ($resultCode) {
            null => 'PENDING', 0 => 'SUCCESS', 1032 => 'CANCELLED', 1037 => 'TIMEOUT', default => 'FAILED',
        };
    }

    /** Read the JSON Safaricom posts to our callback address. Returns null if it is not a callback. */
    public static function parseCallback(array $body): ?array
    {
        $cb = $body['Body']['stkCallback'] ?? null;
        if (!is_array($cb) || empty($cb['CheckoutRequestID'])) {
            return null;
        }
        $receipt = null;
        $amount = null;
        foreach ($cb['CallbackMetadata']['Item'] ?? [] as $item) {
            if (($item['Name'] ?? '') === 'MpesaReceiptNumber') {
                $receipt = (string) ($item['Value'] ?? '');
            }
            if (($item['Name'] ?? '') === 'Amount') {
                $amount = (float) $item['Value'];
            }
        }
        return ['request_id' => (string) $cb['CheckoutRequestID'], 'state' => self::stateFor((int) ($cb['ResultCode'] ?? -1)),
            'message' => (string) ($cb['ResultDesc'] ?? ''), 'receipt' => $receipt, 'amount' => $amount];
    }

    private function password(): array
    {
        $timestamp = (new DateTime('now', new DateTimeZone('Africa/Nairobi')))->format('YmdHis');
        return [base64_encode($this->cfg['shortcode'] . $this->cfg['passkey'] . $timestamp), $timestamp];
    }

    private function accessToken(): string
    {
        if ($this->token === null) {
            $auth = base64_encode($this->cfg['key'] . ':' . $this->cfg['secret']);
            $reply = $this->http('GET', '/oauth/v1/generate?grant_type=client_credentials', null, ['Authorization: Basic ' . $auth]);
            if (empty($reply['access_token'])) {
                throw new RuntimeException('Daraja did not give an access token. Check DARAJA_CONSUMER_KEY and DARAJA_CONSUMER_SECRET.');
            }
            $this->token = $reply['access_token'];
        }
        return $this->token;
    }

    private function call(string $path, array $body, bool $acceptErrorBody = false): array
    {
        return $this->http('POST', $path, $body, ['Authorization: Bearer ' . $this->accessToken()], $acceptErrorBody);
    }

    private function http(string $method, string $path, ?array $body, array $headers, bool $acceptErrorBody = true): array
    {
        $ch = curl_init($this->base . $path);
        $headers[] = 'Content-Type: application/json';
        $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => $headers];
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = json_encode($body);
        }
        if (!empty($this->cfg['ca_bundle'])) {
            $opts[CURLOPT_CAINFO] = $this->cfg['ca_bundle'];    // TLS checking stays ON
        }
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Could not reach Safaricom: ' . $err);
        }
        curl_close($ch);
        $data = json_decode((string) $raw, true);
        return is_array($data) ? $data : [];
    }
}

/** The one place that decides which gateway is used. */
function get_gateway(): SimulatorGateway|DarajaGateway
{
    static $gw = null;
    return $gw ??= use_simulator() ? new SimulatorGateway() : DarajaGateway::fromEnv();
}

function gateway_name(): string
{
    return use_simulator() ? SimulatorGateway::NAME : DarajaGateway::NAME;
}
