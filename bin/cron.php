<?php
// php bin/cron.php [--force] [--job=backup] [--status]
//
// ONE crontab line instead of six: run this every 5 minutes and it starts only the jobs that are due
// (payments-sync every 10 min, rollup hourly, health every 15 min, billing + backup daily, archive weekly; src/Cron.php).
//     */5 * * * *  /usr/bin/php /path/to/w5d/bin/cron.php
// --status  print when each job last ran and change nothing
// --token   print a new random CRON_TOKEN for CRON_MODE=web and change nothing
// --force   run every job now (or only --job=<name>)
// Hosts without any cron: set CRON_MODE=web (an outside pinger opens /cron/<CRON_TOKEN>/) or CRON_MODE=request (see docs/INSTALL.md).
require __DIR__ . '/_boot.php';
use W5D\Cron;

$force = in_array('--force', $argv, true);
$only = null;
foreach ($argv as $a) if (preg_match('/^--job=([a-z\-]+)$/', $a, $m)) $only = $m[1];
if ($only !== null && !isset(Cron::JOBS[$only])) { fwrite(STDERR, 'unknown job; choose one of: ' . implode(', ', array_keys(Cron::JOBS)) . "\n"); exit(2); }

if (in_array('--token', $argv, true)) { echo Cron::newToken(), "\n"; exit(0); }
if (in_array('--status', $argv, true)) {
    $s = Cron::status();
    echo 'mode: ', Cron::mode(), "\n", strtoupper($s['level']), ': ', $s['text'], "\n";
    foreach ($s['rows'] as $r) printf("  %-14s %s\n", $r['id'], $r['age'] === null ? 'never ran' : 'last run ' . $r['age'] . ' s ago by ' . ($r['by'] ?? '?') . ($r['ok'] === false ? ' (FAILED)' : ''));
    exit(0);
}
$bad = 0;
foreach (Cron::runDue('cli', $force, null, $only) as $id => $r) {
    if ($r['ran'] && !$r['ok']) $bad++;
    printf("%-14s %s\n", $id, $r['ran'] ? ($r['ok'] ? 'ran ok' : 'ran WITH ERROR: ' . $r['out']) : $r['out']);
}
exit($bad ? 1 : 0);
