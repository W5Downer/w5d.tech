<?php
// php bin/rollup.php — aggregates raw tables into rollups_daily (the ONLY table dashboards/APIs read). Work lives in src/Jobs.php.
require __DIR__ . '/_boot.php';
$r = \W5D\Jobs::rollup();
echo $r['out'], "\n";
exit($r['exit']);
