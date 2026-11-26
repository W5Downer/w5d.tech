<?php
// php bin/health.php — storage thresholds: warn > 60 MB, refuse uploads > 90 MB. Work lives in src/Jobs.php.
require __DIR__ . '/_boot.php';
$r = \W5D\Jobs::health();
echo $r['out'], "\n";
exit($r['exit']);
