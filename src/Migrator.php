<?php
declare(strict_types=1);

namespace W5D;

/** Applies db/migrations/*.sql in order. Called automatically by DB::pdo() so a fresh deployment never meets "no such table". */
final class Migrator
{
    public static bool $ran = false;

    /** @return string[] names applied now */
    public static function run(\PDO $pdo, bool $force = false): array
    {
        if (self::$ran && !$force) return [];
        self::$ran = true;
        $files = glob(ROOT . '/db/migrations/*.sql') ?: [];
        sort($files);
        $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (name TEXT PRIMARY KEY, applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
        $done = $pdo->query('SELECT name FROM schema_migrations')->fetchAll(\PDO::FETCH_COLUMN);
        $applied = [];
        foreach ($files as $f) {
            $name = basename($f);
            if (in_array($name, $done, true)) continue;
            $pdo->exec('BEGIN IMMEDIATE');
            try {
                // another request may have applied it while we waited for the lock
                $st = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE name=?');
                $st->execute([$name]);
                if (!$st->fetchColumn()) {
                    $pdo->exec((string)file_get_contents($f));
                    $pdo->prepare('INSERT INTO schema_migrations(name) VALUES (?)')->execute([$name]);
                    $applied[] = $name;
                }
                $pdo->exec('COMMIT');
            } catch (\Throwable $e) {
                try { $pdo->exec('ROLLBACK'); } catch (\Throwable) {}
                throw new \RuntimeException("Migration $name failed: " . $e->getMessage(), 0, $e);
            }
        }
        if ($applied && in_array('001_initial.sql', $applied, true)) {
            // fresh database: seed reference data (countries, regions, roles, permissions)
            self::$pending = true;
        }
        return $applied;
    }

    public static bool $pending = false;
}
