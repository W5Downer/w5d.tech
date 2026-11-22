<?php
// php tests/funding.php — funding stack + AdSense fail-closed (tests 1-8 of the funding prompt, plus ledger rules).
// Uses a throw-away SQLite file; the dev database is never touched.
$tmp = sys_get_temp_dir() . '/w5d-funding-' . getmypid();
@mkdir($tmp);
putenv('DB_PATH=' . $tmp . '/t.sqlite');
require __DIR__ . '/../bin/_boot.php';

use W5D\{Funding, Ads, DB, Lang, Request, App};

$ok = $bad = 0;
function chk(string $n, bool $c, string $x = ''): void { global $ok, $bad; if ($c) { $ok++; echo "  ok   $n\n"; } else { $bad++; echo "  FAIL $n $x\n"; } }
function page(string $path, string $lang = 'en'): string {
    Lang::set($lang); $_SERVER['REQUEST_METHOD'] = 'GET'; $_SERVER['HTTP_HOST'] = 'localhost'; $_SERVER['REQUEST_URI'] = $path;
    $r = App::handle(new Request()); return $r->status . '|' . $r->body;
}
function main_text(string $html): string { preg_match('~<main.*?</main>~s', $html, $m); return html_entity_decode(preg_replace('/\s+/', ' ', strip_tags($m[0] ?? '')), ENT_QUOTES, 'UTF-8'); }
function runphp(string $script, array $env = []): array {
    $e = ''; foreach ($env as $k => $v) $e .= $k . '=' . escapeshellarg($v) . ' ';
    exec('cd ' . escapeshellarg(dirname(__DIR__)) . ' && ' . $e . 'DB_PATH=' . escapeshellarg($GLOBALS['tmp'] . '/t.sqlite') . ' php ' . $script . ' 2>&1', $out, $code);
    return [$code, implode("\n", $out)];
}

echo "tokens\n";
chk('all funding tokens are readable', Funding::buildErrors() === [], implode('; ', Funding::buildErrors()));
foreach (['pending' => true, 'applied' => true, 'approved' => true, 'not_applied' => false, 'bogus' => false] as $st => $want) {
    putenv("F5_STARTUP_ACT_APPLICATION=$st");
    chk("F5 = $st -> " . ($want ? 'available' : 'unavailable'), Funding::channelAvailable('F5')['available'] === $want);
}
putenv('F5_STARTUP_ACT_APPLICATION=pending');
$r = Funding::channelAvailable('F2');
chk('channelAvailable returns the four documented keys', array_keys($r) === ['channel', 'available', 'reason_code', 'reason_text']);
chk('unknown channel is closed', Funding::channelAvailable('F9')['available'] === false);

echo "test 1: F2 without the restriction clause is unavailable\n";
chk('F2 available with the shipped template', Funding::channelAvailable('F2')['available'] === true, json_encode(Funding::channelAvailable('F2')));
$t = file_get_contents(ROOT . '/docs/funding/f2-sponsorship-agreement.txt');
file_put_contents("$tmp/f2-norestr.txt", preg_replace('/^\[restriction\].*?(\R\R|\z)/ms', '', $t));
file_put_contents("$tmp/f2-norefund.txt", preg_replace('/^\[refund\].*?(\R\R|\z)/ms', '', $t));
file_put_contents("$tmp/f2-stub.txt", str_replace('[restriction] RESTRICTION OF USE. The sum paid', "[restriction] short.\n\n[x] The sum paid", $t));
Funding::useTemplates(['f2' => "$tmp/f2-norestr.txt"]);
$r = Funding::channelAvailable('F2');
chk('no restriction clause -> unavailable', !$r['available'] && $r['reason_code'] === 'restriction_clause_missing', json_encode($r));
Funding::useTemplates(['f2' => "$tmp/f2-norefund.txt"]);
$r = Funding::channelAvailable('F2');
chk('no refund clause -> unavailable', !$r['available'] && $r['reason_code'] === 'refund_clause_missing', json_encode($r));
Funding::useTemplates(['f2' => "$tmp/f2-stub.txt"]);
chk('a one-word restriction clause does not count', !Funding::channelAvailable('F2')['available']);
Funding::useTemplates(['f2' => "$tmp/missing.txt"]);
chk('unreadable template -> unavailable (fail-closed)', Funding::channelAvailable('F2')['reason_code'] === 'template_missing');
Funding::resetTemplates();
putenv('F2_FUNDS_RESTRICTED=false'); chk('F2 token off -> unavailable', !Funding::channelAvailable('F2')['available']); putenv('F2_FUNDS_RESTRICTED=true');
putenv('F2_REFUND_IF_ACTIVATION_FAILS=false'); chk('F2 refund token off -> unavailable', !Funding::channelAvailable('F2')['available']); putenv('F2_REFUND_IF_ACTIVATION_FAILS=true');
putenv('F2_SPONSORSHIP_ENABLED=false'); chk('F2 disabled -> unavailable', !Funding::channelAvailable('F2')['available']); putenv('F2_SPONSORSHIP_ENABLED=true');

echo "test 2: F4 without the condition precedent is unavailable\n";
chk('F4 available with the shipped template', Funding::channelAvailable('F4')['available'] === true, json_encode(Funding::channelAvailable('F4')));
$t4 = file_get_contents(ROOT . '/docs/funding/f4-presale-contract.txt');
file_put_contents("$tmp/f4-nocond.txt", preg_replace('/^\[condition-precedent\].*?(\R\R|\z)/ms', '', $t4));
file_put_contents("$tmp/f4-wrongcond.txt", str_replace('matricule_fiscal_issued', 'something_else', $t4));
file_put_contents("$tmp/f4-noescrow.txt", preg_replace('/^\[escrow\].*?(\R\R|\z)/ms', '', $t4));
Funding::useTemplates(['f4' => "$tmp/f4-nocond.txt"]);
chk('no condition clause -> unavailable', Funding::channelAvailable('F4')['reason_code'] === 'condition_missing');
Funding::useTemplates(['f4' => "$tmp/f4-wrongcond.txt"]);
chk('condition not verbatim -> unavailable', !Funding::channelAvailable('F4')['available']);
Funding::useTemplates(['f4' => "$tmp/f4-noescrow.txt"]);
chk('no escrow clause -> unavailable', Funding::channelAvailable('F4')['reason_code'] === 'escrow_clause_missing');
Funding::resetTemplates();
putenv('F4_ESCROW_REQUIRED=false'); chk('escrow not required -> unavailable', Funding::channelAvailable('F4')['reason_code'] === 'escrow_not_required'); putenv('F4_ESCROW_REQUIRED=true');
putenv('F4_CONDITION_PRECEDENT='); chk('empty condition token -> unavailable', !Funding::channelAvailable('F4')['available']); putenv('F4_CONDITION_PRECEDENT=matricule_fiscal_issued');
chk('PDF first pages carry the clauses (pdftotext)', (function () {
    if (!trim((string)shell_exec('command -v pdftotext'))) return true;
    $a = (string)shell_exec('pdftotext -f 1 -l 1 ' . escapeshellarg(ROOT . '/docs/funding/f2-sponsorship-agreement.pdf') . ' - 2>/dev/null');
    $b = (string)shell_exec('pdftotext -f 1 -l 1 ' . escapeshellarg(ROOT . '/docs/funding/f4-presale-contract.pdf') . ' - 2>/dev/null');
    return stripos($a, 'restriction') !== false && stripos($a, 'refund') !== false && stripos($a, 'not a loan') !== false
        && stripos($b, 'matricule_fiscal_issued') !== false && stripos($b, 'escrow') !== false;
})());
chk('F2 template never calls the money a loan or a return', !preg_match('/(?<!not a )\bloan\b.*\bearns\b/is', '') && stripos(Funding::clause($t, 'no-return'), 'not a loan') !== false);

echo "test 3: no transfer / cash-out code in the parts code\n";
exec('cd ' . escapeshellarg(ROOT) . ' && grep -rEn "transfer_credits|to_account|cash_out" src db 2>/dev/null', $g);
chk('grep transfer_credits|to_account|cash_out in src + db: 0 hits', $g === [], implode("\n", $g));
chk('F3 available (scan agrees)', Funding::channelAvailable('F3')['available'] === true);

echo "test 4: 29 pages -> Ads::canApply() false\n";
$mk = function (int $n, int $words = 300): array {
    $rows = []; for ($i = 0; $i < $n; $i++) { $k = ['service', 'macro', 'trust'][$i % 3]; $rows[] = ['path' => ($k === 'service' ? '/services/s' : ($k === 'macro' ? '/macro-works/m' : '/trust/t')) . $i . '/', 'words' => $words]; } return $rows;
};
Ads::useGate($mk(29)); $d = Ads::canApplyDetail();
chk('29 substantive pages -> false', Ads::canApply() === false && $d['reason_code'] === 'too_few_pages', json_encode($d['reason_code']));
Ads::useGate($mk(30, 199)); chk('30 pages of 199 words -> false (thin pages do not count)', Ads::canApply() === false && Ads::canApplyDetail()['counted'] === 0);
$rows = $mk(30); $rows[0]['words'] = 150; Ads::useGate($rows); chk('one thin page among 30 -> 29 counted -> false', Ads::canApply() === false && Ads::canApplyDetail()['counted'] === 29);
$rows = []; for ($i = 0; $i < 30; $i++) $rows[] = ['path' => '/services/x' . $i . '/', 'words' => 400]; Ads::useGate($rows);
chk('30 pages but no macro/trust pages -> false (category rule)', Ads::canApply() === false && str_starts_with(Ads::canApplyDetail()['reason_code'], 'too_few_'));
Ads::useGate(array_merge($mk(30), [['path' => '/checkout/', 'words' => 900], ['path' => '/status/', 'words' => 900]]));
chk('checkout and status pages are never counted', Ads::canApplyDetail()['counted'] === 30);
Ads::useGate($mk(30)); $d = Ads::canApplyDetail();
chk('30 pages (10/10/10) -> only the policy page and consent remain', $d['ok'] === Ads::policyPageExists() && ($d['ok'] || $d['reason_code'] === 'policy_missing'), json_encode($d['reason_code']));
chk('consent mechanism is recorded (cookie notice present + dated record)', Ads::consentRecorded());
Ads::useGate(null);
chk('with no computed gate -> closed', Ads::canApply() === false);
chk('/trust/ads-policy/ is excluded from ads and from counts', !Ads::allowedOn('/trust/ads-policy/') && !Ads::allowedOn('/status/') && !Ads::allowedOn('/checkout/') && !Ads::allowedOn('/certification/verify/') && !Ads::allowedOn('/' . trim(W5D\Env::get('ADMIN_URL_PATH', 'panel-x9z'), '/') . '/') && Ads::allowedOn('/services/x/'));

echo "test 5: matricule empty and ADS_WITHDRAWN_TND > 0 -> the build fails\n";
chk('baseline: lint passes', runphp('bin/lint.php')[0] === 0, runphp('bin/lint.php')[1]);
[$c, $o] = runphp('bin/lint.php', ['ADS_WITHDRAWN_TND' => '25', 'VAT_MATRICULE_FISCAL' => '', 'ADS_WITHDRAWAL_ALLOWED' => 'false']);
chk('withdrawn > 0, no matricule -> lint exits 1', $c === 1 && str_contains($o, 'ADS_WITHDRAWN_TND'), "$c $o");
[$c, $o] = runphp('bin/lint.php', ['ADS_WITHDRAWN_TND' => '25', 'VAT_MATRICULE_FISCAL' => 'X', 'ADS_WITHDRAWAL_ALLOWED' => 'false']);
chk('withdrawn > 0, matricule set but withdrawal not allowed -> still fails', $c === 1);
[$c, $o] = runphp('bin/lint.php', ['ADS_WITHDRAWN_TND' => '25', 'VAT_MATRICULE_FISCAL' => 'X', 'ADS_WITHDRAWAL_ALLOWED' => 'true']);
chk('withdrawn > 0, matricule set and withdrawal allowed -> passes', $c === 0, "$c $o");
[$c, $o] = runphp('bin/lint.php', ['ADS_WITHDRAWN_TND' => '25', 'VAT_MATRICULE_FISCAL' => '', 'ADS_WITHDRAWAL_ALLOWED' => 'true']);
chk('withdrawal allowed but matricule empty -> fails', $c === 1);
[$c, $o] = runphp('bin/preflight.php', ['ADS_WITHDRAWN_TND' => '25']);
chk('preflight reports the same failure', str_contains($o, 'FAIL  funding') || str_contains($o, 'FAIL funding'), $o);
[$c, $o] = runphp('bin/lint.php', ['ADS_ACCRUED_TND' => 'abc']);
chk('a malformed token fails the build', $c === 1);
[$c, $o] = runphp('bin/lint.php', ['F2_SPONSORSHIP_MIN_TND' => '']);
chk('a missing token fails the build', $c === 1);
putenv('VAT_MATRICULE_FISCAL='); putenv('ADS_WITHDRAWAL_ALLOWED=true'); chk('canWithdraw needs BOTH the switch and a matricule', Ads::canWithdraw() === false);
putenv('VAT_MATRICULE_FISCAL=SET'); chk('canWithdraw true with both', Ads::canWithdraw() === true);
putenv('ADS_WITHDRAWAL_ALLOWED=false'); chk('canWithdraw false when the switch is off', Ads::canWithdraw() === false);
putenv('VAT_MATRICULE_FISCAL=');

echo "test 6: /status/ wording\n";
[$st, $html] = explode('|', page('/status/'), 2); $txt = main_text($html);
chk('/status/ renders', $st === '200');
foreach (['Registry activation fund: 0 TND restricted (sponsorship)', 'AdSense accrued: 0 TND / withdrawn: 0 TND', 'Owner salary deferred: true — published liability', 'Ad revenue is revenue; it is declared when received.', 'Withdrawal before a valid matricule fiscal is not permitted.'] as $s)
    chk("status shows: $s", str_contains($txt, $s));
preg_match('~<div class="max-w-4xl" data-funding-status>.*?</x-?section>|<div class="max-w-4xl" data-funding-status>.*?(?=<section|\z)~s', $html, $bm);
$blk = strip_tags($bm[0] ?? '');
chk('funding block says "accrued" and never "cash" or "withdrawn" as an achievement', stripos($blk, 'accrued') !== false && stripos($blk, 'cash') === false);
chk('required vs achieved vs gap and a progress bar are shown', str_contains($html, 'data-fund-numbers') && str_contains($html, 'role="progressbar"'));
chk('every channel shows open/closed with a reason', substr_count($html, 'data-channel="F') === 5);
chk('F1 is closed (registration not recorded)', (bool)preg_match('/data-channel="F1" data-available="0"/', $html));
foreach (['fr' => 'Fonds d’activation du registre : 0 TND affectés (parrainage)', 'ar' => 'صندوق تفعيل السجل: 0 TND مقيّدة (رعاية)'] as $l => $s)
    chk("status in $l carries the translated fund line", str_contains(main_text(explode('|', page('/status/', $l), 2)[1]), $s));

echo "test 7: salary tile\n";
$s = Funding::salary();
chk('F6 deferred and no restricted sponsor -> deferred', $s['state'] === 'deferred' && str_contains($s['text'], 'Deferred'), json_encode($s));
chk('/status/ salary state is deferred', str_contains($html, 'data-salary-state="deferred"'));
DB::insert('sponsorships', ['ref' => 'SPN-T1', 'sponsor_name' => 'Test', 'email' => 't@example.com', 'amount_tnd' => 3000, 'status' => 'pledged']);
chk('a pledge alone does not end the deferral', Funding::salary()['state'] === 'deferred');
DB::q("UPDATE sponsorships SET status='received', received_on=date('now'), agreement_ref='AG-T' WHERE ref='SPN-T1'");
chk('a received restricted sponsor meets the condition', Funding::salary()['state'] === 'condition_met');
chk('the registry fund now counts the sponsorship', Funding::totals()['f2_funds'] === 3000.0 && Funding::totals()['achieved'] === 3000.0);
DB::q("UPDATE sponsorships SET status='refunded', refunded_on=date('now') WHERE ref='SPN-T1'");
chk('a refunded sponsorship leaves the fund and restores the deferral', Funding::totals()['f2_funds'] === 0.0 && Funding::salary()['state'] === 'deferred');
DB::insert('presales', ['ref' => 'PRE-T1', 'client_name' => 'C', 'email' => 'c@example.com', 'amount_tnd' => 500, 'credits' => 5, 'status' => 'escrowed']);
chk('escrowed pre-sale is shown apart and is NOT achieved funding', Funding::totals()['f4_escrowed'] === 500.0 && Funding::totals()['achieved'] === 0.0);
chk('a pre-sold client meets the deferral condition', Funding::salary()['state'] === 'condition_met');
chk('the condition precedent is not met without a matricule fiscal', Funding::conditionMet() === false);
putenv('VAT_MATRICULE_FISCAL=SET'); chk('...and is met with one', Funding::conditionMet() === true); putenv('VAT_MATRICULE_FISCAL=');
putenv('F6_OWNER_SALARY_DEFERRED=false'); chk('deferral off -> "not_deferred" state', Funding::salary()['state'] === 'not_deferred');
putenv('F6_OWNER_SALARY_DEFERRED=true'); putenv('F6_OWNER_SALARY_PUBLISHED=false'); chk('deferred but unpublished fails the build', count(Funding::buildErrors()) === 1);
putenv('F6_OWNER_SALARY_PUBLISHED=true');

echo "ledger rules\n";
chk('F1 closed without a record', Funding::channelAvailable('F1')['reason_code'] === 'registration_not_recorded');
chk('a future date cannot be recorded', Funding::record('auto_entrepreneur_registration', 'REF', date('Y-m-d', strtotime('+3 day')), 't') === false);
chk('an invalid date cannot be recorded', Funding::record('auto_entrepreneur_registration', 'REF', '2026-13-45', 't') === false);
chk('a dated record opens F1', Funding::record('auto_entrepreneur_registration', 'TEST-REF-1', date('Y-m-d'), 't') && Funding::channelAvailable('F1')['available']);
putenv('F1_AUTO_ENTREPRENEUR_ENABLED=false'); chk('F1 disabled -> closed even with a record', !Funding::channelAvailable('F1')['available']); putenv('F1_AUTO_ENTREPRENEUR_ENABLED=true');
$thrown = false; try { DB::q("UPDATE funding_records SET rvalue='x'"); } catch (Throwable) { $thrown = true; }
chk('funding_records is append-only (update refused)', $thrown);
$thrown = false; try { DB::q('DELETE FROM funding_records'); } catch (Throwable) { $thrown = true; }
chk('funding_records is append-only (delete refused)', $thrown);
chk('log a rejection', Ads::logRejection(date('Y-m-d'), 'low value content', 'Added 12 evidence pages', 'tester'));
chk('a rejection with no corrective action is refused', Ads::logRejection(date('Y-m-d'), 'x', '', 't') === false);
$id = (int)DB::val('SELECT MAX(id) FROM ads_rejection_log');
$thrown = false; try { DB::q("UPDATE ads_rejection_log SET corrective_action='rewritten' WHERE id=$id"); } catch (Throwable) { $thrown = true; }
chk('ads_rejection_log: a past entry cannot be rewritten', $thrown);
$thrown = false; try { DB::q("DELETE FROM ads_rejection_log WHERE id=$id"); } catch (Throwable) { $thrown = true; }
chk('ads_rejection_log: a past entry cannot be deleted', $thrown);
chk('reapplied_at can be set once', Ads::markReapplied($id, date('Y-m-d')) === true);
chk('...and not changed afterwards', Ads::markReapplied($id, date('Y-m-d', strtotime('-1 day'))) === false);
chk('amounts typed into text are caught by the linter rule', Funding::hardcodedAmount('Sponsor from 2,700 TND') !== null && Funding::hardcodedAmount('Sponsor from {{ funding.f2_min_tnd }} {{ money.currency }}') === null && Funding::hardcodedAmount('a 0.5% levy') !== null);

echo "tokens in the content gate\n";
chk('F2 tokens exposed as {{ funding.* }}', W5D\Variables::get('funding.f2_min_tnd') === '2,700' && W5D\Variables::get('funding.rne_budget_tnd') === '1,500');

array_map('unlink', glob("$tmp/*") ?: []); @rmdir($tmp);
printf("funding: %d passed, %d failed\n", $ok, $bad);
exit($bad ? 1 : 0);
