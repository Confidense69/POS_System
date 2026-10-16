# Groupmate contribution — merge notes

The commit `f8e4e4b "4 modules"` added a single extensionless file named `4 modules`
that was actually **six source files pasted together** with `/////` banner comments.
It also added two byte-identical copies of an unrelated Tailwind assignment
(now moved to `assignments/`). This folder keeps the original paste plus the parts
that were **not** wired in, so nothing is lost and nothing of yours is overwritten.

Original paste: `groupmate/4-modules-original.txt`

## What the paste contained (line ranges)

| Lines | Banner | Real file |
|-------|--------|-----------|
| 1–251, 493–496 | `ADMIN-DASHBOARD PHP` | `pages/admin-dashboard.php` |
| 252–492 | `ADMIN SYSTEMS (JS)` | `js/admin-system.js` |
| 504–531 | `CONFIG.PHP` | `api/config.php` |
| 533–580 | `API.LOGIN` | `api/login.php` |
| 581–638 | `LOGIN.JSSS` | `js/login.js` |
| 639–800 | `SUPPLIERS, TRANSACTIONS, ACCOUNTS, INVENTORY` | `api/pos_system_api.php` |

(The paste was also scrambled: the dashboard's closing `</body></html>` and its
`<script src="../js/admin-system.js">` tags were sitting at the *bottom of the JS
block*, not with the HTML. They have been put back where they belong.)

## Wired into the app (new functionality only)

- **`api/pos_system_api.php`** — NEW backend endpoint. Actions:
  `get_users`, `add_user`, `toggle_user`, `get_products`, `add_product`,
  `restock_product`, `get_suppliers`, `add_supplier`, `checkout`.
  Verified against `database.sql`: every table/column it touches exists
  (`users.is_active`, `products.reorder_level/supplier_id`, `suppliers`,
  `transactions`, `transaction_items`, `restock_log`). Checkout wraps the
  sale + stock deduction in a `beginTransaction()`/`commit()`/`rollBack()`.
- **`js/admin-system.js`** — NEW admin dashboard client logic
  (accounts / inventory / suppliers tables + add modals + POS cart + checkout).
- **`pages/admin-dashboard.php`** — your shell was a 120-line static placeholder
  with `@M3` markers, so his working dashboard was grafted in **on top of it**.
  Your markers are kept as comments, and your extra sidebar links
  (Payroll / Reports / System Logs) are kept as disabled `soon` TODOs.

## NOT wired in — your files were left untouched

His versions of these files **rewrite** code you already wrote, so they were only
saved here for you to cherry-pick by hand:

- `groupmate/api-config.php` — his `api/config.php`. Additive bits worth taking:
  `utf8mb4` charset, `PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC`,
  `exit;` after a failed connection, and it fixes your
  `'Content-Type:  Application/json'` double space.
- `groupmate/api-login.php` — his `api/login.php`. Extra behaviour vs yours:
  `session_start()` moved to the top, empty-input validation, `AND is_active = 1`,
  and it returns `redirect_url`. **Contract note:** your version replies with
  `success` and your `js/login.js` builds the redirect itself; his relies on
  `redirect_url`. Pick one convention — do not mix.
- `groupmate/js-login.js` — his `js/login.js`. Real `fetch` POST (your todolist
  task 5). **Gotcha:** it still reads `document.getElementById('role')`, so it
  breaks the moment you delete the role dropdown (your task 4).

## Notes / things to fix later

- `api/pos_system_api.php` calls `session_start()` but has **no auth guard**, so it
  is currently callable without a session. Add a `$_SESSION['user_id']` check once
  you are happy with it.
- His reply key is `status` (`{status:'success'|'error'}`), whereas `login.php`
  uses `success`. Different endpoints, but worth unifying.
- `add_user` defaults a blank password to `password123`.
- `checkout` hardcodes `payment_method = 'cash'` and does not set `transactions.tax_rate`
  even though it computes an 8% tax.

## Moved out of the app tree

`Another task` and `pages/Sir Jewel assingment` were **byte-identical**
(`SHA256 B19F36C0…`). They are an unrelated standalone Tailwind single-page demo
("OmniSystem Hub") with hardcoded mock data and **zero** `fetch`/API calls — a
different assignment, not part of this POS app. Both were moved into `assignments/`
untouched; you can safely delete one of the two duplicates.
