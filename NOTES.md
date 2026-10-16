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
  (10/16 note: the Live Server PROCESS was still running on :5500 and serves .php as raw TEXT — close it.)

## Core mental model (internalized)
browser JS --fetch()--> PHP endpoint --PDO/SQL--> MySQL, JSON back.
Browser can't touch MySQL directly; PHP is the middleman.
CORS solved once in config.php via: header('Access-Control-Allow-Origin: *');  (now same-origin, header is harmless)

## Files that exist
- api\config.php   -> PDO connection helper (root/no password), JSON + CORS headers,
                      default fetch mode = PDO::FETCH_ASSOC (added 10/16)
- api\products.php -> GET all products, OR ?barcode= single product
- api\pos_system_api.php -> GROUP's action endpoint (get/add users, products, suppliers, checkout).
                      Reply key `status`; requires a session (401 otherwise). See groupmate\README.md
- api\login.php / api\logout.php -> real auth (POST JSON in, session out; logout = 302 to index.html)
- index.html + js\login.js  -> REAL auth now: POSTs to api/login.php, routes via data.role (10/16)
- js\login.js does NOT read the role dropdown anymore (dropdown deleted 10/16)
- pages\admin-dashboard.php  -> guarded + working (groupmate UI grafted onto our shell, @M3 kept)
- pages\manager-dashboard.php / cashier-dashboard.php -> guarded + working (10/16): same php guard
                      as admin, real $_SESSION role/full_name echoed, Logout -> ../api/logout.php
- pages\*-dashboard.html -> DELETED (10/16, commit 59ca10c). Were the ORIGINAL First-Commit shells
                      (mock login routed straight to them); unreferenced, unguarded, and duplicated
                      by the .php versions. Recoverable from git history if ever needed.
- js\admin-system.js -> GROUP's admin dashboard client logic (accounts/inventory/suppliers/POS)
- js\cashier-pos.js  -> WORKING barcode flow: input -> fetch api/products.php?barcode= -> render <tr> rows
- css\style.css, database.sql (schema for everything: users, products, transactions, items, restock_log, employees, salaries, system_logs)
- assignments\ -> out of tree: groupmate's unrelated Tailwind "OmniSystem Hub" homework (duplicate pair)
- groupmate\ -> reference copies of his rewrites (config/login/login.js) + 4-modules-original.txt + README.md
- .gitignore (OS junk + secrets protection), todolist.txt (live M3 task list), NOTES.md (this file)
- pos.png -> the Excel-style feature checklist (modules: Backend/Foundation, POS, Inventory, HR/Payroll, Reports, UI/UX; roles: admin/manager/cashier)

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

## WORKING AGREEMENT (updated 10/16 — "Rule A")
- USER writes ALL BACKEND: PHP endpoints + SQL (the graded part).
- AI writes ALL FRONTEND: HTML, CSS, and the JavaScript that renders/calls the API.
- Supersedes the older "user writes ALL JS/PHP/SQL" rule. Spirit is unchanged: AI never
  invents backend logic behind the user's back, and must explain each backend change in plain
  terms so the user can defend it in a teach-back.
- Every session starts with micro-drills (tiny 5-15min one-concept programs).
- Every milestone ends with teach-back: user explains each file line-by-line.

## M3 STATUS (10/16): essentially COMPLETE
Done: real login (api/login.php), logout, hashed seed passwords, js/login.js POST + role routing,
role dropdown removed, all three dashboard guards + $_SESSION echoes, stale .html removed.
Left: task 9 teach-back (Kyle explains login.php + one guard line-by-line) and a browser
click-through for the admin + manager roles (cashier already confirmed). Then M4+ begins.

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

## TESTING PHP ON WINDOWS POWERSHELL (gotcha, 9/14)
- PowerShell 5.1 STRIPS double-quotes when passing inline JSON to curl.exe -> json_decode fails silently.
- ALWAYS test POST with a file: write body.json ({"username":"admin","password":"password123"}) then:
      curl.exe -s -X POST -H "Content-Type: application/json" -d @C:/Users/kyle/Documents/schoolstuff/code/pos-system/body.json http://localhost/pos/api/login.php
- login.php validated with admin/password123 -> {"status":true,"role":"admin",...} (9/14)
- CONTRACT CHECK: login.php replies key `success` (settled 9/14). $_SESSION keys: user_id, username, full_name, role.

## M3 PROGRESS (9/14 night checkpoint)
- api/login.php: DONE + validated (admin/password123 -> {"success":true}) — reply key `success`
- api/logout.php: DONE + verified (HTTP 302 -> index.html) — user's own code, SO snippet + his trigger
- NEXT SESSION: remove role dropdown -> rewrite js/login.js -> guards @M3 markers -> delete stale .html -> full loop test -> teach-back
- RELEARNED BUG FAMILY: "defining =/= doing" (3rd time): logout() was never called; test on PowerShell = file-based JSON
- Apache restarts needed after httpd.conf changes; Ctrl+F5 after frontend edits (Live Server retired)

## Barcode test values (seeded products)
4800012345678 Rice · 4800012345679 Cooking Oil · 4800012345680 Coke · 4800012345681 Pancit Canton

## GROUP CONTRIBUTION MERGE (10/16)
Groupmate pushed an extensionless file literally named `4 modules` (commit f8e4e4b) plus two
byte-identical copies of an unrelated Tailwind assignment. `4 modules` was SIX source files
pasted together under `/////` banner comments (his dashboard HTML, his admin JS, and his versions
of config.php / login.php / login.js, plus a new action API).
- Split into real files; took only the genuinely NEW work:
  api\pos_system_api.php and js\admin-system.js (schema-verified against database.sql).
- admin-dashboard.php: his working dashboard was grafted onto OUR placeholder shell; our @M3
  markers and extra sidebar links (Payroll/Reports/System Logs, disabled) were kept.
- His REWRITES of config.php / login.php / js/login.js were NOT dropped in — kept in groupmate\.
- Cherry-picked from them, adapted to OUR contract: PDO::FETCH_ASSOC default (config), the
  `AND is_active = 1` login filter, trim + empty-input check, and a real POST login.js that
  routes by data.role. Rejected his `redirect_url` field (our contract is `success` + `role`).
- Added the missing auth guard to pos_system_api.php (it called session_start() but never
  checked the user) -> 401 when no session.
- Moved `Another task` + `pages/Sir Jewel assingment` to assignments\ (unrelated Tailwind demo).
- WHY it arrived as one pasted file: the groupmate does NOT pull/push. He re-DOWNLOADS the repo,
  edits locally, then pastes whole files back. So his copy silently diverges from main and can
  clobber our work (he has none of the PHP/auth/guard work). POLICY going forward: Kyle owns main;
  accept only the specific files that are genuinely NEW (or a diff), NEVER a full-tree drop, and
  re-verify every incoming file against our version before merging.
- Done on branch `groupmate-additions`, fast-forwarded into main, pushed.

## SECURITY HOLE — FIXED (10/16)
pages\manager-dashboard.php and pages\cashier-dashboard.php originally had NO session guard
(both returned HTTP 200 with no session). FIXED: each now opens with the same php block as admin
(session_start + header('Location: ../index.html') + exit when $_SESSION['user_id'] is unset),
echoes $_SESSION['role']/['full_name'], and Logout points at ../api/logout.php.
Verified: all three pages now return HTTP 302 with no session; a manager login renders
role "manager" and the real full_name. (Earlier a grep gave a FALSE POSITIVE here because the
string session_start() appears inside the @M3 instruction comment — see Lessons below.)

## Lessons (10/16 session)
- BROWSER CACHE vs "the file still exists": after deleting pages\*-dashboard.html the files still
  opened with NO 404. Cause: Firefox had cached the month-old js/login.js (routing to .html) AND the
  .html pages, so it never contacted Apache. Proof that settled it: curl got 404, access.log showed
  no fresh browser hit for the .html, and the /pos Alias meant there was no second copy on disk.
  RULE: test in a private window; when files "survive" deletion, curl the URL + read access.log
  BEFORE theorising about where they live.
- GREP/SUBSTRING LIES: a check for `session_start()` matched the text inside an @M3 COMMENT,
  so it reported a guard that did not exist. To verify code, READ the file; don't grep for a
  token that can appear in prose. (Same class of bug as trusting a mental model over the diff.)
- Read the diff: "extra </div>" was real; removing the dropdown block also removed it, and the
  balance check (open vs close counts) is what actually settled it. Count, don't eyeball.
- PowerShell `Out-File/-Encoding utf8` (PS 5.1) writes a UTF-8 BOM at the top of the file.
  A BOM on a .php file = bytes emitted before headers -> "headers already sent", broken
  session_start()/header(). Write files with UTF8Encoding($false) instead.
- git branch is a movable LABEL, not a separate copy of the project. On the branch, its files
  ARE the project; main just has not been moved yet. Merging = moving the label forward.
- PDO::FETCH_BOTH (the default without setAttribute) returns every column TWICE in the JSON
  ({"id":4,"0":4}). One setAttribute line fixes every endpoint that uses bare fetchAll().
- Roles are only admin / manager / cashier — the DB ENUM and the pos.png checklist agree.
