# PayGuard guide

## 1. Run it with the simulator (no internet needed)
1. Install XAMPP. In the XAMPP Control Panel start **Apache** and **MySQL**. (Stop any separately installed MySQL Server: both want port 3306.)
2. Copy this whole folder to `C:\xampp\htdocs\payguard`.
3. Copy `.env.example` to `.env` (leave the defaults).
4. Open a terminal in that folder (VS Code: Terminal > New Terminal) and run:
   `C:\xampp\php\php.exe tools\setup.php`
   It prints your staff username and password. Write them down.
5. Open http://localhost/payguard/ . Pick a failure on the checkout page, pay, then log in as staff and open **Reconciliation**.
6. See the data in phpMyAdmin: http://localhost/phpmyadmin > `payguard`.
7. Run the tests: `C:\xampp\php\php.exe tests\run.php`  (needs the `pdo_sqlite` extension, which XAMPP has).

## 2. Try the real Safaricom sandbox
1. Create a free account at developer.safaricom.co.ke, make an app with "Lipa na M-Pesa Sandbox", and copy the Consumer Key, Consumer Secret and the test Passkey.
2. Install ngrok, then run `ngrok http 80`. Copy the https address it shows.
3. In `.env` set: `USE_SIMULATOR=false`, the three Daraja values, `PUBLIC_BASE_URL=https://<your-ngrok>/payguard`, and a secret `MPESA_CALLBACK_TOKEN` (make one with `php -r "echo bin2hex(random_bytes(16));"`).
4. Check the keys: `C:\xampp\php\php.exe tools\mpesa_check.php`. Then send a KES 1 prompt: `... tools\mpesa_check.php 2547XXXXXXXX`.
5. Pay on the site with a sandbox test number. The receipt page refreshes itself; Safaricom calls `index.php/callback/<token>` and the status changes.
6. If the callback never arrives, press **Reconciliation**: it asks Safaricom directly.

**Till number:** the sandbox test shortcode 174379 is a Paybill-style test, so test in the sandbox with the defaults first. For your real Till, set `DARAJA_TRANSACTION_TYPE=CustomerBuyGoodsOnline`, `DARAJA_SHORTCODE` to your store number and `DARAJA_TILL_NUMBER` to your till (check these against Safaricom's go-live instructions).

If cURL says "SSL certificate problem": download cacert.pem from curl.se/docs/caextract.html, save it, and put its full path in `CA_BUNDLE` in `.env`. Never turn certificate checking off.

## 3. What is and is not proven
- Tested automatically here: the rules, the simulator, reconciliation, login, the Daraja client against a pretend Safaricom, and the callback.
- NOT tested here: your MySQL (tests use SQLite) and the real Safaricom. Steps 1 and 2 above are how you test those. Any mistake in the Daraja request details would show up in step 4.
- The sandbox never takes real money. Going live needs Safaricom approval and a Paybill or Till.

## 4. Fixing common problems
| Message | Fix |
|---|---|
| Cannot reach MySQL | Start MySQL in XAMPP; check `DB_*` in `.env`. |
| Database problem on a page | Run `tools/setup.php`. |
| "these settings in .env are empty" | Fill the named settings, or set `USE_SIMULATOR=true`. |
| Daraja did not give an access token | Wrong Consumer Key or Secret. |
