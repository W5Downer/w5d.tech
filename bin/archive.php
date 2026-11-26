<?php
// php bin/archive.php — removes weekly_reports older than the current ISO week per ARCHIVE_DRIVER, then VACUUM (SQLite). Work lives in src/Jobs.php.
require __DIR__ . '/_boot.php';
$r = \W5D\Jobs::archive();
if ($r['ok']) echo $r['out'], "\n"; else fwrite(STDERR, $r['out'] . "\n");
exit($r['exit']);
