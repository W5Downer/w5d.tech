<?php
declare(strict_types=1);

namespace W5D;

/** Single PDO connection chosen from .env. SQLite is the default. */
final class DB
{
    private static ?\PDO $pdo = null;

    public static function pdo(): \PDO
    {
        if (self::$pdo) return self::$pdo;
        $driver = Env::get('DB_DRIVER', 'sqlite');
        $opt = [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC];
        if ($driver === 'sqlite') {
            $path = Env::get('DB_PATH', 'storage/w5d.sqlite');
            if ($path[0] !== '/') $path = ROOT . '/' . $path;
            $new = !is_file($path);
            if (!is_dir(dirname($path))) @mkdir(dirname($path), 0775, true);
            $pdo = new \PDO('sqlite:' . $path, null, null, $opt);
            if ($new) @chmod($path, 0600);
            foreach (['journal_mode = WAL', 'foreign_keys = ON', 'busy_timeout = 5000', 'synchronous = NORMAL', 'wal_autocheckpoint = 1000'] as $p) {
                $pdo->exec('PRAGMA ' . $p);
            }
            if (Env::get('DB_AUTO_MIGRATE', 'true') !== 'false') Migrator::run($pdo);
        } elseif ($driver === 'mysql' || $driver === 'pgsql') {
            $dsn = sprintf('%s:host=%s;port=%s;dbname=%s%s', $driver, Env::get('DB_HOST', 'localhost'),
                Env::get('DB_PORT', $driver === 'mysql' ? '3306' : '5432'), Env::get('DB_DATABASE', 'w5d'),
                $driver === 'mysql' ? ';charset=' . Env::get('DB_CHARSET', 'utf8mb4') : '');
            $pdo = new \PDO($dsn, Env::get('DB_USERNAME'), Env::get('DB_PASSWORD'), $opt);
        } else {
            throw new \RuntimeException("Unsupported DB_DRIVER: $driver");
        }
        self::$pdo = $pdo;
        if (Migrator::$pending) { Migrator::$pending = false; RefData::sync(); }
        return $pdo;
    }

    public static function available(): bool
    {
        try { self::pdo(); return true; } catch (\Throwable) { return false; }
    }

    public static function q(string $sql, array $p = []): \PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($p);
        return $st;
    }

    public static function all(string $sql, array $p = []): array { return self::q($sql, $p)->fetchAll(); }
    public static function one(string $sql, array $p = []): ?array { return self::q($sql, $p)->fetch() ?: null; }
    public static function val(string $sql, array $p = []): mixed { $r = self::q($sql, $p)->fetch(\PDO::FETCH_NUM); return $r ? $r[0] : null; }

    public static function insert(string $table, array $row): int
    {
        $cols = array_keys($row);
        self::q('INSERT INTO ' . $table . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($row));
        return (int)self::pdo()->lastInsertId();
    }

    /** Forget the open connection (the browser installer switches to the database it has just configured). */
    public static function reset(): void { self::$pdo = null; Migrator::$ran = false; }
}
