<?php
// php bin/migrate.php  — applies db/migrations/*.sql in order (idempotent). Also runs automatically on first DB use.
require __DIR__ . '/_boot.php';
use W5D\{DB, Migrator};
try {
    $applied = Migrator::run(DB::pdo(), true);
    foreach ($applied as $a) echo "applied $a\n";
    echo $applied ? 'migrations applied: ' . count($applied) . "\n" : "nothing to migrate\n";
} catch (Throwable $e) { fwrite(STDERR, 'FAIL ' . $e->getMessage() . "\n"); exit(1); }
