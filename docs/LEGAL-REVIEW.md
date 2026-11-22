# Legal and fiscal review checklist

Status: **nothing in this file has been reviewed by a Tunisian lawyer or accountant.** Every trust page is a plain-language statement of how
W5D operates. None is legal advice. Each page carries a sentence saying so, with the review date (`dates.as_of`) and the interval
(`legal.review_months`, 6 months).

The legal and fiscal figures on the pages come from **tokens**, not from page text. To change one, edit `config/variables.php` (or `.env` for
the funding channels) and rebuild; do not edit page JSON. Each section below says what to confirm, where it is stored and which pages show it.

How to review: open the page in each language, read the tokens' current values below, confirm or correct them, then set `dates.as_of` to the
review date. Record the reviewer's name and date at the bottom of this file.

## 1. Business identity

| Claim | Where | Pages | To confirm |
|---|---|---|---|
| Legal form is `pending` (no registration) | `company.legal_form` (`pending`, `auto_entrepreneur`, `company`) | footer (all pages), `/trust/imprint/`, `/trust/terms/` | Change only after the registration exists and has a date. The footer wording switches with this value. |
| No company-registry number, no matricule fiscal, no postal address are published | page text of `/trust/imprint/` | imprint | Add each one, with its date, only when issued. Never publish a placeholder. Confirm which of these Tunisian law requires on a commercial site's legal notice. |
| Hosting provider is not named on the site | `/trust/imprint/` | imprint, security | Confirm whether a legal notice must name the host, and name it if so. |
| Contact address and hours | `company.privacy_contact`, `company.hours`, `company.response_days` | imprint, privacy, data-rights, contact | Confirm the mailbox is monitored and the answer times are achievable. |

## 2. Consumer rights and refunds

| Claim | Token | Pages | To confirm |
|---|---|---|---|
| Statutory withdrawal window for many distance contracts in Tunisia: 10 working days | `legal.withdrawal_days_tn` | terms, refunds | Which contracts the right covers, which are excluded (digital content already delivered, personalised services), when the clock starts. |
| EU right of withdrawal, 14 calendar days, where it applies | `legal.withdrawal_days_eu` | terms, refunds | Whether and when EU consumer law reaches a Tunisian seller. |
| Money returned within 14 days of a refund decision, through the original channel | `legal.refund_days` | refunds | Achievable for every open channel (bank, D17, Flouci, Konnect, Paymee). |
| A contribution buys access to a path, not a job or a return | text | statement, terms, refunds, investors | That this characterisation is correct and is not an investment offer under Tunisian securities or crowdfunding rules. **Highest-risk item.** |
| Pricing is progressive (money x time) and shown in full before payment | `pricing.*` | statement, pricing | That a price that varies with time and funding level meets Tunisian price-display and consumer rules. |

## 3. Personal data and cookies

| Claim | Token | Pages | To confirm |
|---|---|---|---|
| Contact messages kept 24 months | `privacy.retention_messages_months` | privacy | Proportionate and lawful. |
| Closed accounts erased after 30 days except records the law requires | `privacy.retention_account_days` | privacy, data-rights | Which records must be kept. |
| Orders, invoices and payment records kept 10 years | `privacy.retention_accounting_years` | privacy | The statutory period for accounting records in Tunisia. |
| Server logs kept 30 days | `privacy.retention_logs_days` | privacy, cookies | That the web host really deletes logs at 30 days. **Operational, not just legal.** |
| Referral clicks kept 24 months (hashed marker only) | `privacy.retention_affiliate_months` | privacy, cookies | That the stored value is not personal data. |
| Rights requests completed within 30 days | `privacy.rights_days` | data-rights, privacy | The deadline in Tunisian data-protection law. |
| Cookie choice remembered 6 months | `privacy.consent_months` | cookies | That 6 months is acceptable. |
| Breach notification target 72 hours | `legal.ack_hours` | security, privacy | The notice duty and the authority to notify (Tunisian data-protection authority). Note: the token is named `ack_hours` but is used as the breach target and as the acknowledgement time on the security and corrections pages; split it if the two need different values. |
| Cookie list is complete: `w5d_sid`, `w5d_remember`, `w5d_lang`, `w5d_ref`, and `localStorage` keys `w5d-theme`, `w5d-motion`, `w5d-cookies`, `w5d-consent` | `/trust/cookies/` | cookies | Re-check after any change to cookies or scripts. Optional measurement tags (GA4, GTM, Ads, Meta) load only after consent and owner enabling. reCAPTCHA is **not** consent-gated; confirm that is acceptable. |
| The `consents` table is never written | code | cookies, ads-policy | Consent is stored in the browser only. If a regulator or Google expects a server-side record, that is a code change. |
| The data-protection authority and the applicable law are named correctly | page text | privacy, data-rights, security | Confirm the current law and authority names and the cross-border transfer rules. |

## 4. Funding channels (F1 to F6)

Set in `.env`; the status page shows each as open or closed, with the reason.

| Channel | Setting | To confirm |
|---|---|---|
| F1 auto-entrepreneur | `F1_AUTO_ENTREPRENEUR_ENABLED`, `F1_TAX_RATE=0.005` | The 0.5% rate, the turnover ceiling, the registration steps, and whether the activity qualifies. Open only when the registration is recorded with a date. |
| F2 sponsorship | `F2_SPONSORSHIP_MIN_TND`, `F2_FUNDS_RESTRICTED`, `F2_REFUND_IF_ACTIVATION_FAILS`, template `docs/funding/f2-sponsorship-agreement.*` | The agreement text, the minimum, the restriction and refund clauses. The PDF is a **template**, not a signed contract. |
| F4 pre-sold credits | `F4_CONDITION_PRECEDENT`, `F4_ESCROW_REQUIRED`, template `docs/funding/f4-presale-contract.*` | Whether pre-selling credits before a matricule fiscal is lawful, how escrow is held, and who the escrow agent is. **Not chosen yet.** |
| F5 Startup Act | `F5_STARTUP_ACT_APPLICATION` | Eligibility, label status, benefits. Do not describe it as obtained until it is. |
| Registry fees | `RNE_ACTIVATION_FEE_TND`, `RNE_BUDGET_TND` | Current fees and the budget. |

## 5. Advertising

| Claim | Setting | To confirm |
|---|---|---|
| Ad revenue cannot be withdrawn without a matricule fiscal | `VAT_MATRICULE_FISCAL`, `ADS_WITHDRAWAL_ALLOWED=false` | Tax treatment of foreign ad income and the invoicing needed. |
| Ads never appear on trust, status, checkout, verification, account or owner pages | `Ads::allowedOn` | Re-check against the current AdSense policies. |
| Content gate: 30 substantive pages (200+ words), 5+ service briefs, 5+ macro pages, 5+ trust pages, policy page exists, consent recorded | `ADS_MIN_CONTENT_PAGES`, `ADS_MIN_PAGE_WORDS`; `php bin/ads-gate.php` | The gate is **our** rule and says nothing about whether Google will approve. |

**Honest reading of the gate:** at the last run (`php bin/ads-gate.php`) the gate reports met: 188 pages counted (130 service, 5 macro, 13 trust,
40 other). 130 of those are generated service briefs. Counting them meets our rule, but whether Google treats generated pages as substantive is
a separate question and is **not verified**. `ADS_STATUS` stays `not_applied`; applying to AdSense is a deliberate human decision.

## 6. Content claims to verify as true

These statements on the pages describe processes that must really exist, or be reworded:

- **Accessibility:** the statement says what has and has not been tested. No assistive-technology audit has been done. Do not claim conformance.
- **Editorial:** states that catalogue and QCM content is generated and AI-assisted, and that native-speaker review of French and Arabic is pending.
- **Sampling:** the `quality.sampling_percent` draw, a reviewer who did not write or accept the report, results recorded. No reviews exist yet. The status page says so.
- **Corrections:** acknowledgement within `legal.ack_hours`, decision within `quality.correction_sla_days` (14). Someone must be assigned.
- **Security:** no independent penetration test has been done and no bug bounty exists. `security.txt` lists the contact.
- **Courts and governing law:** the imprint says Tunisian law applies and that the competent courts are confirmed with a Tunisian lawyer. Name them once confirmed.

## 7. Language review

French and Arabic text is hand-written, not machine-translated, but has **not** been reviewed by a native-speaking professional. Arabic legal
terms in particular need a reviewer who knows Tunisian legal usage.

## Review log

| Date | Reviewer | Scope | Outcome |
|---|---|---|---|
| (none yet) | | | |
