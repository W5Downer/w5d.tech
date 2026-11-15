<?php
// php bin/migrate.php  — applies db/migrations/*.sql in order (idempotent).
require __DIR__ . '/_boot.php';
use W5D\DB;
$pdo = DB::pdo();
$pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (name TEXT PRIMARY KEY, applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
$done = array_column(DB::all('SELECT name FROM schema_migrations'), 'name');
$files = glob(ROOT . '/db/migrations/*.sql'); sort($files);
$n = 0;
foreach ($files as $f) {
    $name = basename($f);
    if (in_array($name, $done, true)) continue;
    $pdo->beginTransaction();
    try {
        $pdo->exec((string)file_get_contents($f));
        DB::q('INSERT INTO schema_migrations(name) VALUES (?)', [$name]);
        $pdo->commit();
        echo "applied $name\n"; $n++;
    } catch (Throwable $e) { $pdo->rollBack(); fwrite(STDERR, "FAIL $name: " . $e->getMessage() . "\n"); exit(1); }
}
echo $n ? "migrations applied: $n\n" : "nothing to migrate\n";
