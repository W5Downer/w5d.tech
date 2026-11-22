<?php
/**
 * Single source of truth for every number the site prints.
 * Tokens in content/lang files: {{ money.base_day }}, {{ counts.services }} ...
 * Derived values are computed in src/Variables.php from the 'derive' map.
 * PRICING_* may be overridden from .env, but every change must be dated + logged
 * (pricing_audit). No block may hard-code a price, a year or the 130,000 figure.
 */
return [
    'money' => [
        'currency'       => 'TND',
        'salary_monthly' => 600,     // per expert post, per 30-day month
        'base_day'       => 10,      // price of one part per day at p=0, t=0
        'base_week'      => 70,      // price of one part per week at p=0, t=0
        'license_hour'   => 610.5,
    ],
    'pricing' => [
        'alpha'  => 1.0,   // progress multiplier
        'beta'   => 1.0,   // time multiplier
        'p_prog' => 2.0,   // progress exponent
        'p_time' => 2.0,   // time exponent
        'cap'    => 3.0,   // maximum multiple of base
    ],
    'dates' => [
        'start'      => '2026-11-01',   // programme clock start (t = 0)
        'deadline'   => '2026-12-31',   // programme clock end   (t = 1)
        'offer_year' => 2027,           // "valid before 2027"
        'as_of'      => '2026-11-18',   // date the published figures were last reviewed
        'doctrine'   => '2026-11-18',   // date of the last doctrine revision (docs/CHANGELOG-doctrine.md)
    ],
    'experts' => [
        'per_service'      => 1000,   // ESTIMATE: expert posts per meta service (D3). Review against certification throughput.
        'meta_services'    => 130,    // 5 macro works x 26 services; must equal the number of services in data/content/services (linted)
        'services_per_macro' => 26,
        'span_design'      => 10,     // design span of control (1 manager : 10 experts)
        'span_cap'         => 12,     // hard cap enforced by the portal
        'reviewer_ratio'   => 50,     // reviewer load, experts per reviewer
        'managers_per_service' => 100,
        'team_size'        => 10,
        'current'          => 0,   // overridden by rollups_daily at render
        'developers_today' => 1,   // real delivery footprint today (Tunisia): the owner, who also manages the team
        'managers_today'   => 0,   // managers other than the owner (real, dated figure)
        'departments'      => 16,
    ],
    'quality' => [
        'sampling_percent' => 5,      // fixed by doctrine: lowering it dilutes the integrity layer (D7)
        'rag_red'          => 70,
        'rag_amber'        => 90,
        'correction_sla_days' => 14,
    ],
    'storage' => ['budget_mb' => 100, 'warn_mb' => 60, 'refuse_mb' => 90],
    // Wave cohorts (gate-driven, not calendar-driven). Managers are derived: posts / span_design.
    'waves' => [
        'w0' => ['pilot' => 5,  'posts' => 0,     'managers' => 5],
        'w1' => ['posts' => 1000],
        'w2' => ['posts' => 10000],
    ],
    // Declared unit-economics parameters (not records).
    'economics' => [
        'cost_week_low' => 52, 'cost_week_high' => 54,
        'manager_share' => 41.5, 'reviewer_share' => 6.5, 'tooling_low' => 0.2, 'tooling_high' => 2.3, 'cert_amortised' => 3.8,
        'cost_year_low' => 2700, 'cost_year_high' => 2800,
    ],
    // Revenue gates and rules.
    'gates' => [
        'concentration_max_pct' => 40,   // no single stream above this share at any gate
        'w2_recurring_pct'      => 60,   // subscription + unit revenue vs team cost at W2
        'w3_licensing_pct'      => 30,   // licensing share of total at W3
        'graduate_ceiling'      => 250,  // no graduate-facing price above this without a sponsorship fallback
        'credit_uplift_pct'     => 10,
        'credit_expiry_months'  => 24,
    ],
    // Revenue catalogue prices (TND). Names, inclusions and falsifiers live in data/content/pricing/*.json.
    'cat' => [
        'a1' => 0, 'a2' => 60, 'a3' => 25, 'a4m' => 15, 'a4y' => 120, 'a5' => 250, 'a6' => 30,
        'b1' => 350, 'b2' => 550, 'b3' => 400,
        'c1' => 90, 'c2' => 250, 'c3' => 600, 'c4' => 1400, 'c5' => 1200, 'c6' => 3500, 'c7' => 9000, 'c8' => 300, 'c9' => 35,
        'd2' => 1000, 'd2_credit' => 1100,
        'e1' => 18000, 'e2' => 45000, 'e3' => 90000, 'e4' => 6000,
        'f1' => 10,
        'g1' => 2500, 'g2' => 6000,
        'h1' => 4500, 'h2' => 12000, 'h3' => 3600,
        'i1' => 8000, 'i2' => 45000, 'i3' => 15000,
        'j1' => 3200, 'j2' => 32000, 'j3' => 150000,
    ],
    // Behaviour switches the owner can flip from the dashboard (no deploy needed).
    'flags' => [
        'gate_forms'       => 1,   // 1 = checkout, subscription, applicant registration and manager application need a signed-in account
        'gate_eligibility' => 0,   // 1 = the 30-second eligibility check also needs a signed-in account (default: public)
    ],
    // Affiliate programme (owner-editable in the portal: Variables). Commissions are declared rates, not a promise of income.
    'affiliate' => [
        'enabled'        => 1,     // 0 hides the programme and stops tracking
        'commission_pct' => 5.0,   // % of a referred investor's VERIFIED payment
        'signup_bonus'   => 2.0,   // TND when a referred account confirms its e-mail
        'cert_bonus'     => 5.0,   // TND when a referred account earns its first certificate
        'cookie_days'    => 30,    // how long a ?ref= link is remembered
        'hold_days'      => 14,    // days a commission stays pending before it becomes payable
        'min_payout'     => 50.0,  // TND: smallest payout that can be requested
        'auto_approve'   => 0,     // 1 = new affiliates are active at once; 0 = a person approves them
    ],
    // Certificates: issued only by a passed certification/upgrade QCM round (or by hand by the owner), verifiable by anyone with the code.
    'cert' => [
        'validity_months' => 24,
        'levels'          => 4,
        'verify_per_10min' => 30,   // public lookups allowed per IP per 10 minutes
    ],
    // QCM engine defaults (each round's own rules live in the portal: QCM rules).
    'qcm' => [
        'enabled'            => 1,
        'default_questions'  => 10,
        'default_minutes'    => 10,
        'grace_seconds'      => 15,    // server tolerance after the timer ends
        'bank_target'        => 1000,  // target questions per service (all levels together) for the full bank
        'generated'          => 1,     // 1 = the generated pool (src/QcmGen.php) is drawn from next to the stored questions
        'generated_per_level' => 1000, // generated questions per service per level (0-5000): 130 services x 4 levels x 1000 = 520,000
        'show_answers_practice' => 1,  // 1 = practice rounds reveal the correct answers and explanations
    ],
    // Business identity shown in the footer, e-mails and legal pages. Only facts the owner can stand behind: no invented
    // registration numbers. Registry fields stay empty until the matricule fiscal / RNE entry exists (see Funding, /status/).
    'company' => [
        'brand'       => 'W5D TECH',
        'country'     => 'Tunisia',
        'since'       => 2026,          // first year of operation (copyright range start)
        'legal_form'  => 'pending',     // pending | auto_entrepreneur | company  (drives the footer wording)
        'hours'       => 'Mon-Fri 09:00-17:00 (Africa/Tunis)',
        'response_days' => 5,           // working days to answer a message
        'privacy_contact' => 'owner@w5d.tech',
    ],
    // Retention and legal-reference tokens used by the trust pages. These are COMMITMENTS the owner must be able to keep, and legal
    // references the owner's accountant/lawyer must confirm (see docs/LEGAL-REVIEW.md). Change here, never in the page text.
    'privacy' => [
        'retention_messages_months' => 24,   // contact-form messages and replies
        'retention_account_days'    => 30,   // days after an account is closed before it is erased (except records the law requires)
        'retention_accounting_years' => 10,  // orders, invoices and payment records (accounting-record retention; confirm with the accountant)
        'retention_logs_days'       => 30,   // web-server access logs
        'retention_affiliate_months' => 24,  // referral clicks (hashed marker only)
        'rights_days'               => 30,   // calendar days to complete a data-rights request (acknowledged sooner, see company.response_days)
        'consent_months'            => 6,    // how long a cookie choice is remembered before we ask again
    ],
    'legal' => [
        'withdrawal_days_tn' => 10,          // working days: statutory withdrawal window for many distance contracts in Tunisia (confirm)
        'withdrawal_days_eu' => 14,          // calendar days: EU distance-selling right of withdrawal, where it applies
        'refund_days'        => 14,          // days to return money after a refund decision (same channel as the payment)
        'ack_hours'          => 72,          // breach notification target to the regulator where required
        'review_months'      => 6,           // trust pages are reviewed at least this often
    ],
    'investor' => [
        'min_parts_per_day' => 1,
        'max_parts_per_day' => 1000,
        'max_days'          => 365,
        'contact'           => 'owner@w5d.tech',
    ],
];
