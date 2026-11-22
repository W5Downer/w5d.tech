<?php
declare(strict_types=1);
// php bin/ads-gate.php [--json]   — compute the AdSense content gate from the RENDERED site and cache it (storage/cache/ads-gate.json).
// Counts words inside <main> of every indexable page in English; pages under ADS_MIN_PAGE_WORDS do not count; transaction and credential pages are excluded.
// Exit 0 always (it is a report); use `php bin/ads-gate.php --require` to exit 1 when Ads::canApply() is false.
require __DIR__ . '/_boot.php';

use W5D\{Ads, Funding, Lang, Request, App};
use W5D\Controllers\Docs;

@mkdir(ROOT . '/storage/cache', 0775, true);
Lang::set('en');
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$rows = [];
foreach (array_keys(Docs::sitemapUrls()) as $path) {
    $_SERVER['REQUEST_URI'] = $path;
    try { $res = App::handle(new Request()); } catch (Throwable $e) { continue; }
    if ($res->status !== 200) continue;
    $html = (string)$res->body;
    if (!preg_match('~<main\b[^>]*>(.*?)</main>~s', $html, $m)) continue;
    $t = preg_replace('~<(script|style|template|svg)\b.*?</\1>~s', ' ', $m[1]);
    $t = html_entity_decode(strip_tags(preg_replace('~<[^>]+>~', ' ', $t)), ENT_QUOTES, 'UTF-8');
    $rows[] = ['path' => $path, 'words' => preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\'’\-]*/u', $t)];
}
$g = Ads::evaluate($rows);
$g['fingerprint'] = Ads::fingerprint();
file_put_contents(Ads::cacheFile(), json_encode($g, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
$d = Ads::canApplyDetail();
if (in_array('--json', $argv, true)) { echo json_encode($d + ['gate' => $g], JSON_PRETTY_PRINT), "\n"; }
else {
    printf("pages rendered: %d | substantive (>= %d words): %d of %d needed\n", count($rows), $g['min_words'], $g['counted'], $d['needed']);
    printf("by kind: service %d, macro %d, trust %d, other %d (each of the first three needs >= 5)\n", $g['by_kind']['service'], $g['by_kind']['macro'], $g['by_kind']['trust'], $g['by_kind']['other']);
    printf("thin pages (not counted): %d\n", count($g['thin']));
    foreach (array_slice($g['thin'], 0, 40) as $p) echo "  thin  $p\n";
    printf("Ads::canApply(): %s — %s\n", $d['ok'] ? 'TRUE' : 'false', $d['reason_text']);
}
exit(in_array('--require', $argv, true) && !$d['ok'] ? 1 : 0);
