<?php
// php bin/mail.php test you@example.org [en|fr|ar] [all]   — sends a sample through the configured MAIL_DRIVER
// php bin/mail.php status                                  — driver, sender and recent outbox
// php bin/mail.php suppressed                              — addresses unsubscribed from updates
require __DIR__ . '/_boot.php';
use W5D\{DB, Env, Mail};

$cmd = $argv[1] ?? 'status';
$samples = [
    'verify_email'   => ['name' => 'Test', 'url' => W5D_url('/account/verify/sample/')],
    'password_reset' => ['name' => 'Test', 'url' => W5D_url('/account/reset/sample/'), 'minutes' => 60],
    'order_receipt'  => ['name' => 'Test', 'ref' => 'ORD-TEST', 'parts' => 1, 'days' => 30, 'start' => date('Y-m-d'), 'total' => 30, 'currency' => 'TND', 'd17' => ['amount' => 30, 'gsm' => (string)Env::get('PAYMENT_D17_GSM', '+21600000000'), 'name' => 'W5D'], 'pay_url' => W5D_url('/pay/ORD-TEST/'), 'key' => ''],
    'payment_status' => ['name' => 'Test', 'ref' => 'PAY-TEST', 'order_ref' => 'ORD-TEST', 'amount' => 30, 'currency' => 'TND', 'status' => 'verified', 'note' => '', 'pay_url' => W5D_url('/pay/ORD-TEST/')],
    'form_ack'       => ['kind' => 'newsletter', 'name' => 'Test', 'ref' => ''],
];
function W5D_url(string $p): string { return abs_url($p); }

if ($cmd === 'status') {
    echo 'driver: ' . Env::get('MAIL_DRIVER', 'log') . "\nfrom: " . Env::get('MAIL_FROM_ADDRESS', '(unset)') . "\nhost: " . Env::get('MAIL_HOST', '(unset)') . ':' . Env::get('MAIL_PORT', '') . "\n";
    foreach (DB::all('SELECT created_at, template, status, to_email, subject FROM mail_outbox ORDER BY id DESC LIMIT 10') as $r) echo implode(' | ', $r) . "\n";
    exit(0);
}
if ($cmd === 'suppressed') {
    foreach (DB::all('SELECT email, source, created_at FROM mail_suppressions ORDER BY created_at DESC') as $r) echo implode(' | ', $r) . "\n";
    exit(0);
}
if ($cmd === 'test') {
    $to = $argv[2] ?? ''; $lang = $argv[3] ?? 'en';
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { fwrite(STDERR, "usage: php bin/mail.php test <email> [en|fr|ar] [all]\n"); exit(1); }
    $types = in_array('all', $argv, true) ? array_keys($samples) : ['verify_email'];
    $fail = 0;
    foreach ($types as $t) { $ok = Mail::send($to, $t, $samples[$t], $lang); echo ($ok ? 'sent   ' : 'FAILED ') . $t . "\n"; $fail += $ok ? 0 : 1; }
    echo 'driver=' . Env::get('MAIL_DRIVER', 'log') . ($fail ? "  (see storage/logs/mail-*.log)\n" : "\n");
    exit($fail ? 1 : 0);
}
fwrite(STDERR, "unknown command\n"); exit(1);
