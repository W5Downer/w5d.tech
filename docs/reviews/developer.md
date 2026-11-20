# Review as: Developer / API consumer

**Scope:** public JSON API (`/api/*.json`, `POST /api/quote`, `POST /api/subscriptions`, `POST /api/managers/apply`, `/api/certificates/{code}.json`), CORS, error contract, private OpenAPI at `/openapi.json` and the API tab in dashboards.

## Summary
Good: stable JSON, CORS enabled, rate-limited certificate verification. The first pass found that errors on API paths came back as HTML and that browsers' CORS preflight failed. Both are fixed and guarded. The remaining gaps are localisation of errors and the absence of language-aware URLs.

## What worked
- Unknown API resource: JSON `{"error":"not_found","message":"..."}` 404 with `X-Robots-Tag: noindex`.
- Wrong method: 405 with `Allow` header and JSON `{"error":"method_not_allowed","allow":[...]}`; non-API pages show the 405 page.
- `OPTIONS` -> 204 with `Allow`; on `/api/` and `*.json` also `Access-Control-Allow-Origin: *`, Methods, Headers `Content-Type, X-CSRF-Token`, Max-Age 86400. `GET /api/price.json` sends `Access-Control-Allow-Origin: *` and `Cache-Control: public, max-age=60`.
- Certificate API masks the holder ("Amel T."), reports valid/revoked/expired, and returns 429 after `cert.verify_per_10min` (30) per IP (`cert.py`).
- `/openapi.json` is 401 for guests; `/api/openapi.json` is listed in the post-upload checklist.

## Findings
| ID | Sev | Status | Finding | Repro | Fix / guard |
|---|---|---|---|---|---|
| D-0 | Medium | Fixed | API 404/405 returned HTML; no CORS preflight | `curl -i -X OPTIONS /api/quote`, `curl /api/nope` | `Router`, `not_found()`; `contract.py` |
| D-1 | Low | Open | Validation messages from `/api/quote` are English in all languages and have no stable machine code per field | `POST /api/quote {"parts":0}` | add `code` per error (e.g. `parts_range`) and localise `message` |
| D-2 | Low | Open | `/api/quote` is routed for both GET and POST (a bare GET answers 422 validation); the contract does not say whether GET is supported | `curl -i /api/quote` | decide and document; return 405 for GET if POST-only |
| D-3 | Low | Open | Language is cookie-based; `?lang=` ignored, so API consumers cannot request fr/ar content with a URL | `curl /api/pages/home.json?lang=fr` | support `?lang=` or `Accept-Language` for `/api/*` |
| D-4 | Low | Open | Public OpenAPI subset does not exist; the full spec requires login | `curl -i /openapi.json` (401) | publish a public subset for the open endpoints |

## Not verified
Behaviour behind a real CDN, API versioning policy, throughput.

## Evidence
`contract.py` 34/0 (404/405/OPTIONS/CORS), `cert.py` 55/0, `flows.py` 69/0. Live probe of `/api/price.json` headers confirmed `Access-Control-Allow-Origin: *`.
