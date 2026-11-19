# 2026-11-19 - dashboard API docs, private legacy docs, UX layer, CRUD with ACL

Done
- API docs: description + usage for every item, per audience, inside dashboards (`config/apidocs*`, `ApiGuide`, `partials/api-guide`). Old docs pages/files are kept but private (`Docs::gate`).
- Login/register popups (`authDialog`), quick-find, home path chooser, 404 shortcuts, mobile bottom tab bar.
- CRUD with ACL in dashboards (`config/resources.php`, `src/Crud.php`, 11 resources, audit trail).
- Responsiveness: stacked tables on phones, header search moved into the tab bar below 1024px, audit at 320/360/390 (en/fr/ar).

Fixes found by browser tests
- Quick-find did not close on Esc (focus inside the dialog); modal form kept stale errors between submits; tab bar `display:grid` overrode `lg:hidden`; the "manage" short path pointed at a gated form (now `/for/leader/`).

Tests: smoke 701, flows 69, auth 37, d17 25, dash 142, gate 38, mail 88, crud 60, ui 64, browser 65, mobile 408, viewcache 27, production.sh all green.

Not done / ideas: owner ACL matrix view (`Crud::matrix()` exists), account-role self-CRUD (notifications), per-screen inline help in portal menus.
