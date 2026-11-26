<?php
// php bin/payments-sync.php [--max=50]
// Re-checks online payments (Flouci, Konnect, Paymee) still "pending" after the buyer left the provider page or a webhook was missed.
// Safe to run often (cron: every 10 minutes). Settling is idempotent; the provider is always the source of truth.
// The work is in src/Jobs.php (shared with the web/request cron modes).
require __DIR__ . '/_boot.php';
$max = 50;
foreach ($argv as $a) if (preg_match('/^--max=(\d+)$/', $a, $m)) $max = (int)$m[1];
$r = \W5D\Jobs::paymentsSync($max);
echo $r['out'], "\n";
exit($r['exit']);
