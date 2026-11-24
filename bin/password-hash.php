<?php
declare(strict_types=1);
/**
 * Makes the Argon2id hash for the owner's portal password (WEBMASTER_PASSWORD_HASH in .env).
 *
 *   php bin/password-hash.php            # asks twice, input hidden when the terminal allows it
 *   php bin/password-hash.php --stdin    # reads one line from stdin (for scripts and tests)
 *
 * Prints only the hash (never the password). Paste it into .env as WEBMASTER_PASSWORD_HASH='…' (single quotes: the hash contains $).
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$stdin = in_array('--stdin', $argv, true);
$read = function (string $prompt) use ($stdin): string {
    if ($stdin) return rtrim((string)fgets(STDIN), "\r\n");
    fwrite(STDERR, $prompt);
    $tty = function_exists('posix_isatty') && @posix_isatty(STDIN);
    if ($tty) @shell_exec('stty -echo');
    $l = rtrim((string)fgets(STDIN), "\r\n");
    if ($tty) { @shell_exec('stty echo'); fwrite(STDERR, "\n"); }
    return $l;
};
$p = $read('New owner password: ');
if (!$stdin && $read('Repeat it: ') !== $p) { fwrite(STDERR, "The two entries differ.\n"); exit(1); }
if (strlen($p) < 12) { fwrite(STDERR, "Use at least 12 characters (a long passphrase is best).\n"); exit(1); }
echo password_hash($p, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT), "\n";
