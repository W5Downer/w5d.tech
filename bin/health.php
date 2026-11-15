<?php
// php bin/health.php — storage thresholds: warn > 60 MB, refuse uploads > 90 MB.
require __DIR__ . '/_boot.php';
use W5D\Env;
$p = Env::get('DB_PATH', 'storage/w5d.sqlite'); if ($p[0] !== '/') $p = ROOT . '/' . $p;
$bytes = 0; foreach ([$p, $p . '-wal', $p . '-shm'] as $f) if (is_file($f)) $bytes += filesize($f);
$mb = round($bytes / 1048576, 2);
$state = $mb > 90 ? 'refuse-uploads' : ($mb > 60 ? 'warn' : 'ok');
$out = ['db_mb' => $mb, 'state' => $state, 'checked_at' => date('c')];
@file_put_contents(ROOT . '/storage/health.json', json_encode($out));
echo json_encode($out), "\n";
exit($state === 'ok' ? 0 : 1);
