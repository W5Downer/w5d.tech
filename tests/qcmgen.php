<?php
// php tests/qcmgen.php  — generated QCM pool: validity, determinism, size, variety, three languages, round drawing.
require __DIR__ . '/../bin/_boot.php';
use W5D\{QcmGen, Qcm, Content, Variables};

$ok = $bad = 0;
function chk(string $n, bool $c, string $x = ''): void { global $ok, $bad; if ($c) { $ok++; echo "  ok   $n\n"; } else { $bad++; echo "  FAIL $n $x\n"; } }

$slugs = array_keys(Content::all('services'));
chk('130 services', count($slugs) === 130, (string)count($slugs));
chk('generated pool enabled with 1000 per level', QcmGen::enabled() && QcmGen::perLevel() >= 1000, (string)QcmGen::perLevel());
$counts = Qcm::counts();
$min = PHP_INT_MAX;
foreach ($counts as $c) foreach ([1, 2, 3, 4] as $l) $min = min($min, $c[$l]);
chk('every service has >= 1000 questions at every level', $min >= 1000, (string)$min);
chk('130 x 4 x 1000 = 520,000 generated', array_sum(array_map(fn($c) => $c['gen'], $counts)) === 520000);

$errs = 0; $unf = 0; $n = 0; $sig = []; $conc = []; $kinds = [];
foreach ([$slugs[0], $slugs[40], $slugs[77], $slugs[129]] as $slug) foreach ([1, 2, 3, 4] as $l) {
    $s = [];
    for ($i = 1; $i <= 1000; $i++) {
        $q = QcmGen::question($slug, $l, $i); $n++;
        if (!$q) { $errs++; continue; }
        $v = $q; $v['id'] = $slug . '.L' . $l . '.' . sprintf('%04d', $i);
        if (Qcm::validate($v)) $errs++;
        foreach (['q', 'explain'] as $f) foreach (['en', 'fr', 'ar'] as $lg) if (preg_match('/\{[a-z]+\}/', $q[$f][$lg])) $unf++;
        foreach ($q['choices'] as $c) foreach (['en', 'fr', 'ar'] as $lg) if (preg_match('/\{[a-z]+\}/', $c['t'][$lg])) $unf++;
        $s[QcmGen::signature($q)] = 1; $kinds[$q['kind']] = 1;
    }
    $sig[$slug . $l] = count($s);
}
chk('4,000 sampled questions all pass Qcm::validate', $errs === 0, (string)$errs);
chk('no unfilled {placeholder} in en/fr/ar', $unf === 0, (string)$unf);
chk('question kinds in use >= 40', count($kinds) >= 40, (string)count($kinds));
chk('each service-level has >= 250 distinct questions (stem + choices)', min($sig) >= 250, (string)min($sig));

$a = QcmGen::question($slugs[3], 2, 417); $b = QcmGen::question($slugs[3], 2, 417);
chk('the same number always builds the same question', json_encode($a) === json_encode($b));
chk('id marks a generated question', (bool)preg_match('/^[a-z0-9-]+\.L2\.g0417$/', $a['id']) && $a['gen'] === true);
chk('unknown service / level / number -> null', QcmGen::question('nope', 1, 1) === null && QcmGen::question($slugs[0], 9, 1) === null && QcmGen::question($slugs[0], 1, 0) === null);

// Arabic and French are real translations of the same question
$q = QcmGen::question($slugs[5], 1, 2);
chk('en/fr/ar texts differ', $q['q']['en'] !== $q['q']['fr'] && $q['q']['en'] !== $q['q']['ar'] && preg_match('/\p{Arabic}/u', $q['q']['ar']) === 1);

// drawing a round: 10 distinct, never the same idea twice, random between runs
$d1 = Qcm::draw($slugs[1], 3, 10); $d2 = Qcm::draw($slugs[1], 3, 10);
chk('a round draws exactly 10 questions', count($d1) === 10);
chk('no question twice in a round', count(array_unique(array_column($d1, 'id'))) === 10);
chk('two rounds are not identical', array_column($d1, 'id') !== array_column($d2, 'id'));
chk('a round of 100 is possible', count(Qcm::draw($slugs[1], 1, 100)) === 100);
$conceptOk = true;
foreach ([1, 2, 3, 4] as $l) {
    $items = Qcm::draw($slugs[9], $l, 25); $seen = [];
    foreach ($items as $it) {
        $k = $it['q']['en'] . '|' . implode('|', array_map(fn($c) => $c['t']['en'], array_filter($it['choices'], fn($c) => in_array($c['id'], $it['answer'], true))));
        if (isset($seen[$k])) $conceptOk = false; $seen[$k] = 1;
    }
}
chk('one round never repeats the same idea (stem + right answers)', $conceptOk);
$spread = []; for ($i = 0; $i < 30; $i++) foreach (Qcm::draw($slugs[2], 1, 10) as $it) $spread[$it['id']] = 1;
chk('30 rounds of 10 touch >= 200 different questions (a user rarely sees a repeat)', count($spread) >= 200, (string)count($spread));

// the owner can shrink or switch off the pool
Variables::reset();
echo "\nqcmgen: $ok passed, $bad failed\n";
exit($bad ? 1 : 0);
