<?php
/**
 * Third-party integrations. Everything here is owner-configurable in the portal (Integrations menu); nothing is secret in this file.
 * An integration is active only when it is ON and every required field is valid (src/Integrations.php).
 * Tracking integrations (ga4, gtm, gads, meta) load only AFTER the visitor accepts the cookie banner, and never on the owner portal,
 * the account area, payment pages or the unsubscribe page. The Content-Security-Policy gains each integration's domains only while it is active.
 * "track" => true marks integrations that need visitor consent. Field types: bool | text | secret | select | int | url.
 */
return [
    'ga4' => [
        'name' => 'Google Analytics 4', 'icon' => 'chart-column', 'track' => true,
        'doc' => 'Page-view and lead statistics. Loads gtag.js after the visitor accepts the cookie banner. Find the ID in Analytics → Admin → Data streams (looks like G-XXXXXXXXXX). Do not enable both this and Google Tag Manager with a GA4 tag inside it: pages would be counted twice.',
        'fields' => [
            ['k' => 'measurement_id', 't' => 'text', 'label' => 'Measurement ID (G-…)', 'req' => true, 'pattern' => '/^G-[A-Z0-9]{6,12}$/', 'hint' => 'G-XXXXXXXXXX'],
            ['k' => 'signals', 't' => 'bool', 'label' => 'Allow Google signals / ad personalisation (off = more private)', 'default' => '0'],
        ],
    ],
    'gtm' => [
        'name' => 'Google Tag Manager', 'icon' => 'tags', 'track' => true,
        'doc' => 'Container for tags you manage in Google Tag Manager. Loads gtm.js after consent. Any other vendor your container loads must be allowed below, or the browser will block it (Content-Security-Policy).',
        'fields' => [
            ['k' => 'container_id', 't' => 'text', 'label' => 'Container ID (GTM-…)', 'req' => true, 'pattern' => '/^GTM-[A-Z0-9]{4,10}$/', 'hint' => 'GTM-XXXXXXX'],
            ['k' => 'extra_origins', 't' => 'text', 'label' => 'Extra https origins your tags need (space-separated, optional)', 'default' => '', 'pattern' => '/^(https:\/\/[A-Za-z0-9.\-*]+(:\d+)?)( https:\/\/[A-Za-z0-9.\-*]+(:\d+)?){0,9}$/', 'hint' => 'https://static.example.com https://api.example.com'],
        ],
    ],
    'gads' => [
        'name' => 'Google Ads (remarketing and conversions)', 'icon' => 'megaphone', 'track' => true,
        'doc' => 'Google Ads tag (AW-…) with an optional conversion label, fired on the lead confirmation pages (join thanks, checkout done). Loads after consent. Conversions carry no amount and no personal data.',
        'fields' => [
            ['k' => 'conversion_id', 't' => 'text', 'label' => 'Google Ads ID (AW-…)', 'req' => true, 'pattern' => '/^AW-\d{6,12}$/', 'hint' => 'AW-123456789'],
            ['k' => 'conversion_label', 't' => 'text', 'label' => 'Conversion label (optional)', 'default' => '', 'pattern' => '/^[A-Za-z0-9_\-]{6,40}$/'],
            ['k' => 'signals', 't' => 'bool', 'label' => 'Allow ad personalisation (remarketing)', 'default' => '0'],
        ],
    ],
    'meta' => [
        'name' => 'Meta Pixel (Facebook / Instagram ads)', 'icon' => 'share-2', 'track' => true,
        'doc' => 'Measures ad results on Meta. Loads after consent; sends PageView everywhere and Lead on the confirmation pages.',
        'fields' => [
            ['k' => 'pixel_id', 't' => 'text', 'label' => 'Pixel ID (digits)', 'req' => true, 'pattern' => '/^\d{8,20}$/', 'hint' => '1234567890123456'],
        ],
    ],
    'recaptcha' => [
        'name' => 'Google reCAPTCHA (public forms)', 'icon' => 'shield-check', 'track' => false,
        'doc' => 'Bot protection on every public form: contact, newsletter, join/eligibility, managers, app registration, checkout, subscription, subscription lookup, sign-in, create account, resend, forgot password and reset password, plus the public JSON write API (send the token as "g-recaptcha-response"). v3 is invisible and scored; v2 shows the “I am not a robot” box. The secret stays on the server (encrypted). Google test keys for staging are in Google’s reCAPTCHA FAQ. If Google cannot be reached, the choice below decides whether forms stay open; the hidden honeypot and the per-IP rate limit always apply.',
        'fields' => [
            ['k' => 'version', 't' => 'select', 'label' => 'Version', 'options' => ['3' => 'v3 — invisible, score-based (recommended)', '2' => 'v2 — “I am not a robot” checkbox'], 'default' => '3'],
            ['k' => 'site_key', 't' => 'text', 'label' => 'Site key (public)', 'req' => true, 'pattern' => '/^[A-Za-z0-9_\-]{20,80}$/'],
            ['k' => 'secret_key', 't' => 'secret', 'label' => 'Secret key (private)', 'req' => true],
            ['k' => 'threshold', 't' => 'int', 'label' => 'v3 minimum score, percent (higher = stricter)', 'default' => '50', 'min' => 10, 'max' => 90],
            ['k' => 'domain', 't' => 'select', 'label' => 'Script domain', 'options' => ['www.google.com' => 'google.com (default)', 'www.recaptcha.net' => 'recaptcha.net (where google.com is blocked)'], 'default' => 'www.google.com'],
            ['k' => 'on_outage', 't' => 'select', 'label' => 'If Google cannot be reached', 'options' => ['open' => 'Let the form through (rate limit + honeypot still apply)', 'closed' => 'Block the form until Google answers'], 'default' => 'open'],
            ['k' => 'api', 't' => 'bool', 'label' => 'Also require the token on the public JSON write API (send "g-recaptcha-response"); off = the API keeps only the rate limit and honeypot', 'default' => '0'],
            ['k' => 'admin_login', 't' => 'bool', 'label' => 'Also protect the owner/staff portal sign-in (risk: a Google outage with “block” mode would lock you out; keep “let through” if you enable this)', 'default' => '0'],
            ['k' => 'verify_url', 't' => 'url', 'label' => 'Verification URL override (leave empty for the default of the script domain)', 'default' => ''],
        ],
    ],
    'webmaster' => [
        'name' => 'Search-engine and domain verification', 'icon' => 'search', 'track' => false,
        'doc' => 'Adds the verification meta tags that Google Search Console, Bing Webmaster Tools and Meta Business require. No script, no cookie, no consent needed. Paste only the token (the content="…" part).',
        'fields' => [
            ['k' => 'google', 't' => 'text', 'label' => 'Google Search Console token', 'default' => '', 'pattern' => '/^[A-Za-z0-9_\-]{20,80}$/'],
            ['k' => 'bing', 't' => 'text', 'label' => 'Bing Webmaster token', 'default' => '', 'pattern' => '/^[A-Fa-f0-9]{20,64}$/'],
            ['k' => 'facebook', 't' => 'text', 'label' => 'Meta domain-verification token', 'default' => '', 'pattern' => '/^[A-Za-z0-9]{20,60}$/'],
        ],
    ],
];
