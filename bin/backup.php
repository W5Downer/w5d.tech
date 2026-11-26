<?php
// php bin/backup.php — consistent SQLite snapshot (VACUUM INTO). BACKUP_DRIVER=external must ship the file off-server. Work lives in src/Jobs.php.
require __DIR__ . '/_boot.php';
$r = \W5D\Jobs::backup();
echo $r['out'], "\n";
exit($r['exit']);
