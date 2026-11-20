# W5D audience reviews (for AI developer agents)

Each file is a review written *acting as* that audience, with findings, repro steps and fixes. Generated 2026-11-20.

| Audience | File |
|---|---|
| Owner (portal super-user) | [owner.md](owner.md) |
| Admin (staff, ACL-limited) | [admin.md](admin.md) |
| Leader (public account role) | [leader.md](leader.md) |
| Manager (account role) | [manager.md](manager.md) |
| Member | [member.md](member.md) |
| Investor | [investor.md](investor.md) |
| Customer | [customer.md](customer.md) |
| User (basic account) | [user.md](user.md) |
| Developer / API consumer | [developer.md](developer.md) |
| Guest / public visitor | [guest.md](guest.md) |

## Cross-cutting backlog for developer agents (priority order)
1. Create `tests/qcm.py`, `tests/cert.py`, `tests/affiliate.py` (stdlib, use `tests/_acct.py`): QCM start/answer/submit/timeout, exact-set scoring, requirement gating (cooldown, max_per_day, min_level, min_tasks, min_practice_pct, min_affiliates), fee pass -> D17 verify -> start, task approve, graded rounds hide answers; affiliate apply/approve/`?ref=` click/signup bonus/commission/hold/payout/self-referral refusal; certificate issue/verify page + JSON (masked name, 429, revoked/expired)/print/revoke; bank CRUD/import/export; ACL and CSRF on every POST.
2. Add a crud.py case for `qcm-rules`.
3. Decide on language-prefixed URLs (`/ar/`, `/fr/` currently 404) and a public OpenAPI subset.
4. Role-differentiated dashboards (leader/manager/investor look alike).
5. Human visual review of mobile/RTL on the new pages.
