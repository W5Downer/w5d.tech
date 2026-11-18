# Progressive pricing (money x time)

Single source of truth: `src/Pricing.php` (the ONLY place a part price is computed) and `config/variables.php` (`money.*`, `pricing.*`, `dates.*`).
Owner overrides are stored in `variable_overrides` and audited in `variable_audit` / `pricing_audit` (admin menu `progressive-pricing` and `variables`).

## Function

```
price = base * (1 + alpha * p^p_prog) * (1 + beta * t^p_time)      capped at cap * base, rounded to 2 decimals
p = clamp(experts_current / experts_target, 0, 1)                   Pricing::progress()
t = clamp((now - start) / (deadline - start), 0, 1)                 Pricing::timeShare()  (timezone Africa/Tunis)
```

| Variable | Path | Default |
|---|---|---|
| base per day / week | `money.base_day` / `money.base_week` | 10 / 70 TND |
| alpha, beta | `pricing.alpha`, `pricing.beta` | 1.0, 1.0 |
| exponents | `pricing.p_prog`, `pricing.p_time` | 2.0, 2.0 |
| cap | `pricing.cap` | 3.0 (x base) -> 30 TND/day, 210 TND/week |
| programme clock | `dates.start` / `dates.deadline` | 2026-11-01 / 2026-12-31 |

## Where it is used

| Surface | Code |
|---|---|
| Live price JSON | `GET /api/price.json` -> `Pricing::snapshot()` |
| Published curve | `GET /api/price/curve.json` -> `Pricing::curve()` (p and t move together) |
| Quote | `GET|POST /api/quote?parts_per_day=&days=&start_on=&cadence=daily|weekly` -> `Checkout::quote()` |
| Checkout wizard, subscription | `src/controllers/Checkout.php` (same `quote()`) |
| Charts / tables / pills | blocks `price-chart`, `price-table`, component `price-pill` |
| Owner editor | admin menu `progressive-pricing` (publishes new constants, writes an audit row, effective immediately) |

## Rules (enforced by `php bin/lint.php`)

1. No template or content file may hard-code a price, the `130,000` figure or the year `2027`; use tokens (`{{ money.base_day }}`, `{{ counts.services }}`, `{{ dates.offer_year }}`).
2. A change to alpha/beta/exponents/cap must carry a reason (public audit trail).
3. Honesty: a part funds a path, never a job. Every price surface links to `/trust/statement/`.

## Quote semantics (important, previously mis-tested)

- A charge dated **today** (Africa/Tunis calendar day) is priced at the live instant, so `1 part x 1 day starting today == today.unit == /api/price.json day`.
- Later charges are forecasts at 12:00 Africa/Tunis using the CURRENT `p`; the real price is recomputed on the day.
- `cadence=weekly` => `ceil(days/7)` charges, each priced with `base_week`.
- `schedule.total = round(sum(round(unit*parts, 2)), 2)`.
- Inputs are validated against `investor.min_parts_per_day / max_parts_per_day / max_days` (422 with localised errors).
- **Test pitfall:** the server uses `Africa/Tunis`, the test runner may use UTC. `tests/flows.py` now computes "today" in `Africa/Tunis`; between 23:00 and 24:00 UTC the dates differ and a naive `date.today()` fails.

## Worked values (canonical defaults)

| p = t | day | week |
|---:|---:|---:|
| 0.00 | 10.00 | 70.00 |
| 0.25 | 11.29 | 79.02 |
| 0.50 | 15.63 | 109.38 |
| 0.75 | 24.41 | 170.90 |
| 0.90 / 1.00 | 30.00 (cap) | 210.00 (cap) |

Verify any time: `curl -s localhost:8080/api/price/curve.json`.
