# Note: completion pass + production test (2026-11-18)

Handoff for agents: see `/AGENTS.md` (single source for commands, rules, open items).

## Closed from the 2026-11-17 open list
1. `flows.py` quote test: not a pricing bug. The server prices "today" in `Africa/Tunis`, the runner used UTC; between 23:00 and 24:00 UTC the dates differ. Test now uses Africa/Tunis. (Quote code in `Checkout::quote` / `Pricing::schedule` was correct.)
2. Newsletter list: already shows `unsubscribed` rows (`communications` menu); verified with a seeded row.
3. Admin placeholders: the 13 "Read-only preview" menus now list real rows. `flows.py` checks all 30 DB-less/DB menus render with no placeholder and no `password_hash`/`$argon2`.
4. Docs written: `docs/pricing.md`, `templates.md`, `design.md`, `ontology-six-layers.md`.
5. `tests/viewcache.php` (27 checks). Found while writing it: `Env::get()` prefers real env vars over `.env`, so tests must set explicit values, never unset.

## Added
`tests/production.sh` (+ `production.py`, `tls_proxy.py`, `_prod/sitecustomize.py`), `bin/preflight.php`, HSTS (production + https APP_URL), `X-Powered-By` removal, `W5D_OWNER_PASS` / `SMOKE_CURL_OPTS` test overrides.

## Results
Dev: lint OK; viewcache 27/0; smoke 720/0; flows 69/0; auth 37/0; d17 25/0; dash 105/0; gate 38/0; mail 88/0; ui 64/0; mobile 330/0.
Production simulation (`bash tests/production.sh`): 17 steps, 0 failed, ALL GREEN; preflight 0 FAIL / 4 WARN (expected: log mail driver, demo D17 number, localhost URL override, backups cron).

## Still open
See AGENTS.md section 7 (real-host verification, owner credentials, final social art, admin edit forms, a11y automation).
