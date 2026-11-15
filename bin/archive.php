<?php
// php bin/archive.php — removes weekly_reports older than the current ISO week per ARCHIVE_DRIVER, then VACUUM (SQLite).
require __DIR__ . '/_boot.php';
use W5D\{DB, Env};
$driver = Env::get('ARCHIVE_DRIVER', 'prune');
$week = date('o-\WW');
$old = (int)DB::val("SELECT COUNT(*) FROM weekly_reports WHERE week_id < ?", [$week]);
if ($old && $driver === 'local') {
    $f = ROOT . '/storage/archive/weekly_reports-' . date('Ymd-His') . '.jsonl';
    $h = fopen($f, 'w');
    foreach (DB::q("SELECT * FROM weekly_reports WHERE week_id < ?", [$week]) as $r) fwrite($h, json_encode($r, JSON_UNESCAPED_UNICODE) . "\n");
    fclose($h);
} elseif ($old && $driver === 'external') {
    fwrite(STDERR, "ARCHIVE_DRIVER=external: no S3 adapter is configured in this build; refusing to delete $old rows.\n");
    exit(2);
}
DB::q("DELETE FROM weekly_reports WHERE week_id < ?", [$week]);
if (Env::get('DB_DRIVER', 'sqlite') === 'sqlite') DB::pdo()->exec('VACUUM');
echo "archived/removed $old rows (driver=$driver)\n";
