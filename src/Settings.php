<?php
declare(strict_types=1);

namespace W5D;

/**
 * Owner-managed key/value settings stored in SQLite (table app_settings).
 * Secrets (API keys, private tokens) are encrypted at rest with libsodium secretbox, keyed from APP_KEY / storage/app.key,
 * never rendered back to a page, and never written to the change log.
 */
final class Settings
{
    private static ?array $cache = null;

    private static function boxKey(): string { return hash('sha256', 'w5d-settings-v1|' . Secret::key(), true); }

    public static function encrypt(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return 'enc1:' . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, self::boxKey()));
    }

    public static function decrypt(string $stored): ?string
    {
        if (!str_starts_with($stored, 'enc1:')) return null;
        $raw = base64_decode(substr($stored, 5), true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) return null;
        $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), self::boxKey());
        return $plain === false ? null : $plain;
    }

    private static function load(): array
    {
        if (self::$cache !== null) return self::$cache;
        $out = [];
        try {
            if (DB::available()) foreach (DB::all('SELECT skey, value, is_secret FROM app_settings') as $r) $out[$r['skey']] = ['v' => $r['value'], 's' => (int)$r['is_secret']];
        } catch (\Throwable) {}
        return self::$cache = $out;
    }

    public static function reset(): void { self::$cache = null; }

    /** Plain value (secrets decrypted) or $default. Empty string counts as "not set". */
    public static function get(string $key, ?string $default = null): ?string
    {
        $r = self::load()[$key] ?? null;
        if ($r === null || $r['v'] === '') return $default;
        if ($r['s']) return self::decrypt($r['v']) ?? $default;
        return $r['v'];
    }

    public static function has(string $key): bool { return self::get($key) !== null; }
    public static function bool(string $key, ?bool $default = null): ?bool
    {
        $v = self::get($key);
        return $v === null ? $default : in_array($v, ['1', 'true', 'on', 'yes'], true);
    }

    /** True when a row exists, whatever its value (used to tell "owner said off" from "never configured"). */
    public static function isSet(string $key): bool { return isset(self::load()[$key]) && self::load()[$key]['v'] !== ''; }

    /** Write (or clear when $value is null/''); audited. Secret values are encrypted and never logged. */
    public static function set(string $key, ?string $value, bool $secret, string $by, string $note = ''): bool
    {
        $old = self::load()[$key] ?? null;
        $value = $value === null ? '' : $value;
        $oldPlain = $old === null ? '' : ($old['s'] ? ($old['v'] === '' ? '' : '(secret)') : $old['v']);
        if ($value === '' && $old === null) return false;
        if (!$secret && $old !== null && !$old['s'] && $old['v'] === $value) return false;
        if ($value === '') {
            DB::q('DELETE FROM app_settings WHERE skey = ?', [$key]);
        } else {
            $stored = $secret ? self::encrypt($value) : $value;
            DB::q('INSERT INTO app_settings(skey, value, is_secret, updated_by, updated_at) VALUES (?,?,?,?,CURRENT_TIMESTAMP) ON CONFLICT(skey) DO UPDATE SET value = excluded.value, is_secret = excluded.is_secret, updated_by = excluded.updated_by, updated_at = CURRENT_TIMESTAMP', [$key, $stored, $secret ? 1 : 0, $by]);
        }
        DB::insert('settings_audit', ['skey' => $key, 'action' => ($secret ? 'secret_' : '') . ($value === '' ? 'clear' : 'set'), 'old_value' => $secret ? null : $oldPlain, 'new_value' => $secret ? null : $value, 'changed_by' => $by, 'note' => mb_substr($note, 0, 400)]);
        self::reset();
        return true;
    }
}
