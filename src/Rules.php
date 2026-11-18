<?php
declare(strict_types=1);

/**
 * The one rule for turning a gateway answer into a new transaction status.
 * Pure function: no database, no network, so it is easy to test.
 *
 * $state: SUCCESS | CANCELLED | FAILED | TIMEOUT | PENDING
 * Returns ['outcome' => ..., 'status' => ..., 'message' => ..., 'receipt' => ..., 'incident' => ...]
 *   outcome  ignored   the payment was already final (e.g. a repeated callback)
 *            unchanged the gateway says it is still processing
 *            updated   the status changed
 *            flagged   status set to FLAGGED because the amounts disagree
 */
function apply_gateway_result(array $txn, string $state, string $message, ?string $receipt = null, ?float $paidAmount = null): array
{
    $result = [
        'outcome'  => 'ignored',
        'status'   => $txn['status'],
        'message'  => $txn['gateway_message'] ?? null,
        'receipt'  => $txn['mpesa_receipt'] ?? null,
        'incident' => null,
    ];
    if (!in_array($txn['status'], ['PENDING', 'TIMEOUT'], true)) {
        return $result;                                   // only unfinished payments may change
    }
    if ($state === 'PENDING') {
        return ['outcome' => 'unchanged'] + $result;
    }
    $result['message'] = $message;

    if ($state === 'SUCCESS') {
        $result['receipt'] = $receipt ?? $result['receipt'];
        if ($paidAmount !== null && abs($paidAmount - (float) $txn['amount']) > 0.009) {
            $result['outcome'] = 'flagged';
            $result['status']  = 'FLAGGED';
            $result['incident'] = sprintf('Amount mismatch: order KES %.2f, gateway reports KES %.2f.',
                (float) $txn['amount'], $paidAmount);
            return $result;
        }
        $result['outcome'] = 'updated';
        $result['status']  = 'SUCCESS';
        return $result;
    }
    if (in_array($state, ['CANCELLED', 'FAILED', 'TIMEOUT'], true)) {
        $result['outcome'] = 'updated';
        $result['status']  = $state;
        return $result;
    }
    throw new InvalidArgumentException("Unknown gateway state: $state");
}
