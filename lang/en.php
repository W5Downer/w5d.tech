<?php
/** UI chrome only (English). Content lives in data/content/*.json as {en,fr,ar}. Tokens: {{ path.to.var }}. */
return [
    'brand' => ['tagline' => 'World Five Dimensions'],
    'nav' => [
        'macro' => 'Five macro works', 'services' => '130 services', 'projects' => 'Projects', 'invest' => 'Invest', 'join' => 'Join',
        'company' => 'Company', 'developers' => 'Developers', 'agents' => 'AI agents', 'pricing' => 'Pricing', 'menu' => 'Menu',
        'theme' => 'Toggle theme', 'language' => 'Language', 'skip' => 'Skip to content', 'managers' => 'Become a manager',
        'trust' => 'Trust & limits', 'investors' => 'Investors', 'partners' => 'Partners', 'about' => 'About', 'careers' => 'Careers',
        'faq' => 'FAQ', 'contact' => 'Contact', 'status' => 'Status', 'how' => 'How it works', 'ontology' => 'Ontology',
        'subscription' => 'Daily subscription', 'checkout' => 'Checkout', 'tunisia' => 'Tunisia hub',
    ],
    'cta' => [
        'invest' => 'Take a part today', 'quote' => 'See today\'s price', 'register' => 'Register now', 'eligibility' => 'Check my eligibility',
        'manager' => 'Apply as manager', 'dossier' => 'Request the dossier', 'read_limits' => 'Read the declared limits', 'open' => 'Open',
        'back_home' => 'Back home', 'contact' => 'Write to the right desk', 'subscribe' => 'Start a daily subscription', 'next' => 'Next',
        'back' => 'Back', 'confirm' => 'Confirm', 'send' => 'Send', 'explore' => 'Explore', 'learn' => 'Learn more', 'apply_service' => 'Apply to this service',
        'view_project' => 'View project', 'compare' => 'Compare', 'download' => 'Download', 'copy' => 'Copy',
    ],
    'limits' => [
        'title' => 'Declared limits',
        'jobs' => 'A part is an investment in a path, not a job. W5D does not promise employment.',
        'design' => '"{{ d.experts_target }} experts" is a design equation ({{ counts.macros }} × {{ experts.services_per_macro }} × {{ experts.per_service }}), not a headcount. Experts certified today: {{ d.experts_current }}.',
        'returns' => 'No return, dividend or resale value is promised. Parts fund a published programme; the price function is public.',
        'link' => '/trust/statement/',
    ],
    'footer' => [
        'license' => 'W5D Admin Dashboard licensed at {{ money.license_hour }} TND/hour of work. Promotional offer valid before {{ dates.offer_year }}.',
        'rights' => 'W5D TECH. Figures as of {{ dates.as_of }}.',
        'legal' => 'Legal', 'privacy' => 'Privacy', 'terms' => 'Terms', 'security' => 'Security',
    ],
    'price' => [
        'per_day' => 'per part per day', 'per_week' => 'per part per week', 'progress' => 'Programme progress', 'time' => 'Time elapsed',
        'cap' => 'Cap', 'base' => 'Base', 'today' => 'Today', 'formula' => 'price = base × (1 + α·p^k) × (1 + β·t^m), capped',
        'live' => 'Live from the published function', 'at_p0' => 'Price at the start', 'at_p1' => 'Price at the cap',
    ],
    'lbl' => [
        'name' => 'Full name', 'email' => 'Email', 'phone' => 'Phone', 'org' => 'Organisation', 'message' => 'Message', 'subject' => 'Subject',
        'desk' => 'Desk', 'macro' => 'Macro work', 'service' => 'Service', 'region' => 'Governorate', 'years' => 'Years of experience',
        'team' => 'Largest team led', 'profile' => 'Professional profile URL', 'motivation' => 'Why this role?', 'scenario' => 'Scenario answer',
        'start' => 'Start date', 'parts' => 'Parts per day', 'days' => 'Number of days', 'cadence' => 'Cadence', 'daily' => 'Daily', 'weekly' => 'Weekly',
        'kind' => 'Investor type', 'individual' => 'Individual', 'company' => 'Company', 'diploma' => 'Diploma level', 'field' => 'Field of study',
        'age' => 'Age band', 'accept' => 'I have read the declared limits', 'required' => 'Required', 'optional' => 'Optional', 'any' => 'Any',
        'step' => 'Step', 'of' => 'of', 'total' => 'Total', 'unit' => 'Unit price', 'schedule' => 'Daily schedule', 'date' => 'Date', 'amount' => 'Amount',
        'status' => 'Status', 'ref' => 'Reference', 'token' => 'Management key', 'pause' => 'Pause', 'resume' => 'Resume', 'cancel' => 'Cancel',
        'manage' => 'Manage', 'search' => 'Search', 'save' => 'Save', 'none' => 'Nothing here yet.',
    ],
    'msg' => [
        'thanks' => 'Thank you. Your request is recorded.', 'error' => 'Please correct the highlighted fields.', 'csrf' => 'Session expired. Reload and try again.',
        'rate' => 'Too many attempts. Try again later.', 'demo' => 'Demo mode: no payment is taken. Your pledge is recorded and no money moves.',
        'dup_email' => 'This email is already registered.', 'invalid_email' => 'Enter a valid email address.', 'not_found' => 'We could not find that page.',
    ],
    'admin' => [
        'title' => 'Owner portal', 'login' => 'Sign in', 'logout' => 'Sign out', 'user' => 'Username', 'pass' => 'Password', 'forbidden' => 'You do not have access to this menu.',
        'country' => 'Country', 'rows' => 'rows', 'page' => 'Page', 'publish' => 'Publish', 'demo_data' => 'Seed demo data', 'read_only' => 'Read-only preview',
    ],
];
