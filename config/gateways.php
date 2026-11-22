<?php
/**
 * Payment methods. Everything here is owner-configurable in the portal (Payment methods menu); nothing is secret in this file.
 * kind: manual = the buyer sends money and a person verifies; online = hosted payment page + server-side verification.
 * Field types: bool | text | secret | select | int | url. A "req" field must be filled for the method to be offered.
 * Secrets are stored encrypted (src/Settings.php) and are never shown again after saving.
 */
return [
    'd17' => [
        'name' => 'D17 (La Poste Tunisienne wallet)', 'kind' => 'manual', 'tn_only' => true, 'icon' => 'smartphone',
        'doc' => 'Manual: the buyer sends the amount to your D17 number and gives the transaction reference; an administrator verifies it. Tunisian residents only.',
        'env' => ['enabled' => 'PAYMENT_D17_ENABLED', 'gsm' => 'PAYMENT_D17_GSM', 'holder' => 'PAYMENT_D17_NAME', 'note' => 'PAYMENT_D17_NOTE'],
        'fields' => [
            ['k' => 'gsm', 't' => 'text', 'label' => 'D17 receiving number (+216…)', 'req' => true, 'pattern' => '/^\+?\d{8,15}$/'],
            ['k' => 'holder', 't' => 'text', 'label' => 'Account holder name', 'default' => 'W5D TECH'],
            ['k' => 'note', 't' => 'text', 'label' => 'Note shown to the buyer', 'default' => ''],
        ],
    ],
    'bank' => [
        'name' => 'Bank transfer (RIB / IBAN)', 'kind' => 'manual', 'icon' => 'landmark',
        'doc' => 'Manual: the buyer wires the amount to your account and enters the transfer reference; an administrator verifies it on your bank statement.',
        'fields' => [
            ['k' => 'bank_name', 't' => 'text', 'label' => 'Bank name', 'default' => ''],
            ['k' => 'holder', 't' => 'text', 'label' => 'Account holder', 'req' => true],
            ['k' => 'iban', 't' => 'text', 'label' => 'RIB / IBAN', 'req' => true, 'pattern' => '/^[A-Za-z0-9 ]{10,40}$/'],
            ['k' => 'bic', 't' => 'text', 'label' => 'BIC / SWIFT (optional)', 'pattern' => '/^[A-Za-z0-9]{0,11}$/'],
            ['k' => 'note', 't' => 'text', 'label' => 'Note shown to the buyer', 'default' => ''],
        ],
    ],
    'flouci' => [
        'name' => 'Flouci (business API)', 'kind' => 'online', 'icon' => 'wallet', 'currency' => 'TND', 'unit' => 'millimes',
        'doc' => 'Hosted page (Flouci wallet, bank card/e-dinar). Auth: Bearer PUBLIC:PRIVATE. Same base URL for sandbox and production: only the keys change. Webhook is signed (X-Flouci-Signature) with your private token and always re-verified server-side. Docs: docs.flouci.com.',
        'webhook' => true,
        'fields' => [
            ['k' => 'public', 't' => 'text', 'label' => 'Public token (app token)', 'req' => true, 'pattern' => '/^[A-Za-z0-9_\-\.]{6,200}$/'],
            ['k' => 'private', 't' => 'secret', 'label' => 'Private token (app secret)', 'req' => true],
            ['k' => 'base_url', 't' => 'url', 'label' => 'API base URL', 'default' => 'https://developers.flouci.com'],
            ['k' => 'webhook_version', 't' => 'select', 'label' => 'Webhook format', 'options' => ['2' => '2 — signed JSON POST (recommended)', '1' => '1 — signed GET'], 'default' => '2'],
            ['k' => 'accept_card', 't' => 'bool', 'label' => 'Accept bank cards on the Flouci page', 'default' => '1'],
            ['k' => 'timeout', 't' => 'int', 'label' => 'Payment session timeout (seconds)', 'default' => '1200', 'min' => 120, 'max' => 86400],
        ],
    ],
    'konnect' => [
        'name' => 'Konnect (wallet, card, e-DINAR)', 'kind' => 'online', 'icon' => 'credit-card', 'currency' => 'TND', 'unit' => 'millimes',
        'doc' => 'Hosted page. Header x-api-key; receiverWalletId from your Konnect dashboard. The webhook only carries a payment reference: the status is always re-read from Konnect. Docs: docs.konnect.network.',
        'webhook' => true,
        'fields' => [
            ['k' => 'mode', 't' => 'select', 'label' => 'Environment', 'options' => ['sandbox' => 'Sandbox (test money)', 'live' => 'Live (real money)'], 'default' => 'sandbox'],
            ['k' => 'api_key', 't' => 'secret', 'label' => 'API key', 'req' => true],
            ['k' => 'wallet_id', 't' => 'text', 'label' => 'Receiver wallet ID', 'req' => true, 'pattern' => '/^[A-Za-z0-9_\-]{6,80}$/'],
            ['k' => 'base_url', 't' => 'url', 'label' => 'API base URL override (leave empty for the default of the environment)', 'default' => ''],
            ['k' => 'lifespan', 't' => 'int', 'label' => 'Payment lifespan (minutes)', 'default' => '20', 'min' => 5, 'max' => 1440],
        ],
    ],
    'paymee' => [
        'name' => 'Paymee (wallet, card, e-DINAR)', 'kind' => 'online', 'icon' => 'banknote', 'currency' => 'TND', 'unit' => 'dinars',
        'doc' => 'Hosted page. Header Authorization: Token API_TOKEN; vendor = your account ID. Webhook check_sum = md5(token + status + API token) is checked, then the status is re-read from Paymee. Docs: paymee.tn.',
        'webhook' => true,
        'fields' => [
            ['k' => 'mode', 't' => 'select', 'label' => 'Environment', 'options' => ['sandbox' => 'Sandbox (test money)', 'live' => 'Live (real money)'], 'default' => 'sandbox'],
            ['k' => 'api_token', 't' => 'secret', 'label' => 'API token', 'req' => true],
            ['k' => 'vendor', 't' => 'text', 'label' => 'Vendor (account ID)', 'req' => true, 'pattern' => '/^[A-Za-z0-9_\-]{1,40}$/'],
            ['k' => 'base_url', 't' => 'url', 'label' => 'API base URL override (leave empty for the default of the environment)', 'default' => ''],
        ],
    ],
];
