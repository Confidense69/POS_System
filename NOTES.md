# POS-Computer Accounting System — Session Handoff

## Stack & environment
- Frontend: HTML + Bootstrap 5 + vanilla JS, served by APACHE via alias /pos
  (repo: C:\Users\kyle\Documents\schoolstuff\code\pos-system  ->  http://localhost/pos/)
- SAME ORIGIN as the PHP API now (no CORS needed, sessions/cookies just work)
- Backend: PHP 8.2 (ships with XAMPP at C:\xampp\php), runs on Apache
- DB: MySQL via XAMPP (phpMyAdmin at http://localhost/phpmyadmin)
- API lives INSIDE the repo at api/ (portable: git pull on any XAMPP machine)
  -> http://localhost/pos/api/...
- Apache alias config: C:\xampp\apache\conf\httpd.conf -> Alias /pos "C:/Users/kyle/Documents/schoolstuff/code/pos-system"
  (Windows note: after editing httpd.conf, restart Apache via XAMPP Control Panel — it's not a service)
- LAPTOP SETUP (paste under <IfModule alias_module> in httpd.conf, restart Apache, import database.sql):
      Alias /pos "C:/Users/kyle/Documents/schoolstuff/code/pos-system"
      <Directory "C:/Users/kyle/Documents/schoolstuff/code/pos-system">
          Options Indexes FollowSymLinks
          AllowOverride All
          Require all granted
      </Directory>
- Live Server (port 5500) RETIRED for this app; the browser now just hits http://localhost/pos/

## Core mental model (internalized)
browser JS --fetch()--> PHP endpoint --PDO/SQL--> MySQL, JSON back.
Browser can't touch MySQL directly; PHP is the middleman.
CORS solved once in config.php via: header('Access-Control-Allow-Origin: *');  (now same-origin, header is harmless)

## Files that exist
- api\config.php   -> PDO connection helper (root/no password), JSON + CORS headers
- api\products.php -> GET all products, OR ?barcode= single product
- index.html + js\login.js  (static login, routes by role dropdown — NOT real auth yet)
- pages\admin-dashboard.php / manager-dashboard.php / cashier-dashboard.php  (shells w/ @M3 guard markers)
- js\cashier-pos.js  -> WORKING barcode flow: input -> fetch api/products.php?barcode= -> render <tr> rows
- css\style.css, database.sql (schema for everything: users, products, transactions, items, restock_log, employees, salaries, system_logs)
- .gitignore (OS junk + secrets protection), todolist.txt (live M3 task list), NOTES.md (this file)

## Milestones DONE
- M1: DB connected, products.php returns 4 seeded products as JSON
- M2: fetch loop in test.html renders products (learned async/await, DOM build, typeof gotchas)
- M2.5: cashier barcode flow working (addByBarcode + renderProduct + alert for not-found)
- M2.5B (9/14): user rebuilt script from memory; found + fixed: event wiring placement, comma operator, URL scheme http, parseFloat order, textContent assignment, classList.add unification. Scratch deleted. Deltas captured. Ready for M3.

## Known lessons (recall-don't-relearn)
- PDO: prepared statements always (prepare/execute/fetchAll), use PDO::FETCH_ASSOC or you get duplicate keys
- DECIMAL columns come back as STRINGS -> parseFloat(price) before math
- getElementById is exact-match: HTML id and JS name MUST be identical (hrs wasted on salesTableBody vs saleTableBody)
- PHP $stmt = the PreparedStatement you know from Java JDBC
- $_GET['x'] = the query-string parameter (Java: request.getParameter)
- classList.add() safer than className += ""

## Cleanups DONE (recall session wrap-up)
- [x] scratch files deleted (test.js + cashier-dashboard2.html)
- [x] all cells use classList.add() (qty text-center; price/total text-end) — no more className +=
- [x] cashier-pos.js re-audited vs user's own rebuild; deltas captured

## Recall session fresh lessons (9/14) — deltas found in first rewrite
- Event listeners: `addEventListener('event', fn)` — hand over the function WITHOUT parens; `fn()` calls it NOW and registers undefined (click did nothing = no errors).
- Listeners must live OUTSIDE/after the function as siblings, not inside it — inside = never registered at load.
- Comma operator `a, b` returns b: `if (e.key === 'Enter', fn())` fires fn() on EVERY key and checks the wrong thing. Use `if (e.key === 'Enter') fn()`.
- fetch URL scheme is exact: `http://` — browser rejects `htpp`/`htpps` and prints `URL scheme "..." is not supported`; READ the error string, it quotes the offender.
- Parameters: `function renderProduct(p)` receives the value from `renderProduct(rq[0])`. No param = function has no data to render (built empty cells).
- A row of cells needs home: createElement('tr') -> fill tds -> append tds to tr -> append tr to tbody. Cells without a row vanish.
- parseFloat FIRST then toFixed: `parseFloat(p.price).toFixed(2)`, NOT `parseFloat((p.price).toFixed(2))` (strings have no .toFixed).
- Product objects have name/barcode/price/stock_qty fields — no `lt`. Verify field names with console.log(rq[0]).
- Computing a value ≠ displaying it: `parseFloat(p.price).toFixed(2)` builds a string and discards it unless you assign `cell.textContent = ...`. (Name cell worked; price cells were empty for this exact reason.)
- http vs https: XAMPP/dev = plain `http`. `https` is production-with-certificates. Error strings quote the offender; just read them.

## The LEARNING WORKFLOW (user's contract with any AI)
- I (user) write ALL the JS/PHP/SQL myself.
- AI gives STEP DECOMPOSITION + POINTERS (e.g. "look up encodeURIComponent on MDN"),
  NOT finished code. No copy-paste answers.
- AI may do CSS / page shells only (design is delegated, interface contract stays mine).
- Every session starts with micro-drills (tiny 5-15min one-concept programs).
- Every milestone ends with teach-back: user explains each file line-by-line.

## NEXT GOAL: M3 — real login
- PHP sessions (server remembers who you are across requests; session_start, $_SESSION, logout = session_destroy)
- password_hash() / password_verify() on the users table (admin/manager/cashier, seed pw "password123")
- api/login.php (POST username/password -> check users -> start session -> return role)   <- IN THE REPO api/
- api/logout.php  (in the repo api/ too)
- Page guards: tops of pages/*.php, @M3 markers -> session_start + header('Location: ../index.html') if no $_SESSION['user_id']
- Update js/login.js to POST JSON to /pos/api/login.php and redirect to pages/<role>-dashboard.php
- The role dropdown in index.html gets DELETED (role comes from DB)

## Portability (M3.5)
- Everything needed to run lives in the git repo: frontend + api/ + database.sql + .gitignore
- On the laptop: install XAMPP, clone repo, add the /pos Alias to httpd.conf, import database.sql
- Repo hygiene rule: check `git status` + stage specific paths before every commit (never blind `git add .`)
- Never commit: real DB passwords, API keys, .env. root/blank localhost creds are fine for school.

## Barcode test values (seeded products)
4800012345678 Rice · 4800012345679 Cooking Oil · 4800012345680 Coke · 4800012345681 Pancit Canton