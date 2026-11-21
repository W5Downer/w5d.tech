<?php
// php bin/payments-sync.php [--max=50]
// Re-checks online payments (Flouci, Konnect, Paymee) still "pending" after the buyer left the provider page or a webhook was missed.
// Safe to run often (cron: every 10 minutes). Settling is idempotent; the provider is always the source of truth.
require __DIR__ . '/_boot.php';
use W5D\{Payments};

$max = 50;
foreach ($argv as $a) if (preg_match('/^--max=(\d+)$/', $a, $m)) $max = max(1, min(500, (int)$m[1]));
$rows = Payments::pendingOnline($max);
$n = 0; $paid = 0;
foreach ($rows as $p) {
    // skip payments younger than 60 s: the buyer may still be on the provider page
    if (!empty($p['started_at']) && time() - strtotime((string)$p['started_at'] . ' UTC') < 60) continue;
    $before = $p['status'];
    $res = Payments::sync($p, 'cron');
    $n++;
    $after = \W5D\DB::one('SELECT status FROM payments WHERE id = ?', [$p['id']])['status'] ?? $before;
    if ($after === 'verified') $paid++;
    echo $p['ref'] . ' ' . $p['method'] . ' ' . $before . ' -> ' . $after . "\n";
}
echo "checked $n, confirmed $paid\n";
