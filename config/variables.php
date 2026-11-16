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
        'as_of'      => '2026-11-15',   // date the published figures were last reviewed
    ],
    'experts' => [
        'per_service'      => 1000,
        'services_per_macro' => 26,
        'managers_per_service' => 100,
        'team_size'        => 10,
        'current'          => 0,   // overridden by rollups_daily at render
        'developers_today' => 26,  // real delivery footprint today (Tunisia)
        'departments'      => 16,
    ],
    'quality' => ['sampling_percent' => 5],
    'investor' => [
        'min_parts_per_day' => 1,
        'max_parts_per_day' => 1000,
        'max_days'          => 365,
        'contact'           => 'owner@w5d.tech',
    ],
];
