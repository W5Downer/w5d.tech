<?php
// php bin/staff-code.php <team-email>   — break-glass: print a one-time sign-in code WITHOUT sending mail.
//
// Use it only when the mail relay is down or the mailbox is out of reach, from a shell on the server (this file refuses to run
// from a web request). The code is the same kind the e-mail carries: 6 digits, single use, valid for STAFF_CODE_MINUTES,
// 5 wrong attempts kill it, and a new code (by e-mail or by this script) invalidates the previous one.
// The address must already be authorised (the owner address, or an active team user on the team domain): this script cannot
// create access, only deliver a code to someone who already has it.
// Every use is written to the audit log (actor "console") and to storage/logs/staff-code-YYYY-MM-DD.log (address + time, never the code).
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/_boot.php';
use W5D\{DB, StaffAuth};

$email = trim((string)($argv[1] ?? ''));
if ($email === '' || in_array($email, ['-h', '--help'], true)) {
    fwrite(STDERR, "usage: php bin/staff-code.php <team-email>\n  prints a 6-digit one-time code for an authorised team address (owner, admin, leader, manager, member).\n");
    exit(2);
}
if (!StaffAuth::isStaffEmail($email)) { fwrite(STDERR, "Not a team address: it must end with @" . StaffAuth::domain() . ".\n"); exit(1); }
if (!DB::available()) { fwrite(STDERR, "Database unavailable.\n"); exit(1); }
$code = StaffAuth::issueLocal($email);
if ($code === null) { fwrite(STDERR, "This address is not authorised (unknown, inactive, or not a team role). Add it in the portal Team menu first.\n"); exit(1); }

$who = StaffAuth::normalize($email);
try { DB::insert('audit_log', ['country_id' => 'tn', 'actor' => 'console', 'action' => 'login.code.console', 'subject' => $who, 'detail' => 'one-time code issued from the server shell (bin/staff-code.php)']); } catch (Throwable) {}
$dir = ROOT . '/storage/logs';
if (!is_dir($dir)) @mkdir($dir, 0775, true);
@file_put_contents($dir . '/staff-code-' . date('Y-m-d') . '.log', '[' . date('c') . '] code issued for ' . $who . ' by ' . (function_exists('posix_getpwuid') && function_exists('posix_geteuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? 'cli') : 'cli') . "\n", FILE_APPEND);

fwrite(STDOUT, "Code for $who: " . substr($code, 0, 3) . ' ' . substr($code, 3) . '   (valid ' . StaffAuth::ttlMinutes() . " minutes, works once)\n");
fwrite(STDERR, "Enter it on the team sign-in page. It replaced any earlier code for this address.\n");
exit(0);
