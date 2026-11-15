<?php
// php bin/backup.php — consistent SQLite snapshot (VACUUM INTO). BACKUP_DRIVER=external must ship the file off-server.
require __DIR__ . '/_boot.php';
use W5D\{DB, Env};
if (Env::get('BACKUP_DRIVER', 'external') === 'external') { echo "BACKUP_DRIVER=external: configure your off-server shipper to pull storage/backups/latest.sqlite\n"; }
$dest = ROOT . '/storage/backups/latest.sqlite';
@unlink($dest);
DB::pdo()->exec("VACUUM INTO '" . str_replace("'", "''", $dest) . "'");
chmod($dest, 0600);
echo "snapshot: $dest\n";
