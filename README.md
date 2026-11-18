# PayGuard (PHP + MySQL)

Every payment is saved as a permanent record BEFORE the payment gateway is called. If the gateway
fails, times out or loses its callback, the record is still there, and staff can reconcile it later.

## Map of the project (read in this order)
| File | What it is |
|---|---|
| `index.php` | The single entry point. Lists every page. |
| `pages/*.php` | One file per screen (checkout, receipt, login, dashboard, transactions, incidents, reconciliation, callback). |
| `src/Payments.php` | The payment story: record, call gateway, save answer, reconcile. **The heart of the project.** |
| `src/Gateways.php` | Simulator and real Safaricom Daraja, same methods. `USE_SIMULATOR` picks one. |
| `src/Rules.php` | The one rule that turns a gateway answer into a status. |
| `database/schema.sql` | The MySQL tables. |
| `tools/setup.php` | One-time setup: database, tables, first login. |
| `tools/mpesa_check.php` | Checks your Daraja keys. |
| `tests/run.php` | Automatic tests. |
| `assets/` | CSS and JavaScript. |

Languages: **PHP** (everything), **SQL** (`schema.sql`), **HTML** (inside the pages), **CSS**, **JavaScript** (tiny).

## Quick start
See `docs/GUIDE.md`. Short version: copy to `C:\xampp\htdocs\payguard`, start Apache and MySQL in XAMPP,
copy `.env.example` to `.env`, run `php tools/setup.php`, open http://localhost/payguard/.
