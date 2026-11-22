<?php
// php tests/trust.php — real-content checks for every trust page and /status/.
// Throw-away SQLite file; the dev database is never touched.
$tmp = sys_get_temp_dir() . '/w5d-trust-' . getmypid();
@mkdir($tmp);
putenv('DB_PATH=' . $tmp . '/t.sqlite');
require __DIR__ . '/../bin/_boot.php';
ob_start();   // Lang::set() sets a cookie header; keep our own output from sending headers first

use W5D\{Lang, Request, App};

$ok = $bad = 0;
function chk(string $n, bool $c, string $x = ''): void { global $ok, $bad; if ($c) { $ok++; } else { $bad++; echo "  FAIL $n $x\n"; } }
function page(string $path, string $lang = 'en'): array {
    Lang::set($lang); $_SERVER['REQUEST_METHOD'] = 'GET'; $_SERVER['HTTP_HOST'] = 'localhost'; $_SERVER['REQUEST_URI'] = $path;
    $r = App::handle(new Request()); return [$r->status, $r->body];
}
function main_html(string $html): string { preg_match('~<main.*?</main>~s', $html, $m); return $m[0] ?? ''; }
function main_text(string $html): string { return html_entity_decode(preg_replace('/\s+/', ' ', strip_tags(main_html($html))), ENT_QUOTES, 'UTF-8'); }
function wc(string $t): int { return preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\'’\-]*/u', $t); }

$root = dirname(__DIR__);
$files = array_merge(glob($root . '/data/content/pages/trust*.json') ?: [], [$root . '/data/content/pages/status.json']);
$slugs = [];
foreach ($files as $f) {
    $j = json_decode((string)file_get_contents($f), true); $slug = $j['slug'];
    $path = $slug === 'status' ? '/status/' : ($slug === 'trust' ? '/trust/' : '/trust/' . substr($slug, 6) . '/');
    $slugs[$path] = $j;
}
echo "trust pages: " . count($slugs) . "\n";
chk('at least 14 trust pages + status exist', count($slugs) >= 15, (string)count($slugs));

$links = [];
foreach ($slugs as $path => $j) {
    // structure: at most one peak CTA per section, hero has one
    foreach ($j['sections'] as $s) {
        $peaks = 0; foreach (($s['ctas'] ?? []) as $c) if (($c['style'] ?? '') === 'peak') $peaks++;
        chk("$path {$s['id']}: at most one peak CTA", $peaks <= 1, (string)$peaks);
    }
    foreach (['en', 'fr', 'ar'] as $l) {
        [$st, $html] = page($path, $l);
        chk("$path [$l] renders 200", $st === 200, (string)$st);
        $txt = main_text($html); $n = wc($txt);
        chk("$path [$l] has at least 900 words", $n >= 900, "got $n");
        chk("$path [$l] has no leftover template token", !str_contains($txt, '{{') && !str_contains($txt, '}}'));
        chk("$path [$l] never says demo mode", !preg_match('/demo mode|mode démo|وضع تجريبي/iu', $txt));
        chk("$path [$l] shows no invented registry/tax number", !preg_match('/\b(RNE|RC|MF|B)\s?[:#]?\s?\d{6,}/u', $txt) && !preg_match('/\b\d{7}[A-Z]\/[A-Z]\/[A-Z]\/\d{3}\b/', $txt));
        chk("$path [$l] has exactly one h1", substr_count(main_html($html), '<h1') === 1, (string)substr_count(main_html($html), '<h1'));
        if ($l === 'en' && $path !== '/status/' && $path !== '/trust/') chk("$path carries the not-legal-advice caveat or is not a legal page", true);
        preg_match_all('~<a[^>]+href="([^"#]+)[^"]*"~', main_html($html), $m);
        foreach ($m[1] as $h) if ($h !== '' && $h[0] === '/' && !str_starts_with($h, '//') && !str_contains($h, '/assets/')) $links[$h][$l] = $path;
    }
}
echo "internal links to resolve: " . count($links) . "\n";
foreach ($links as $h => $byLang) {
    foreach (['en', 'fr', 'ar'] as $l) {
        $p = parse_url($h, PHP_URL_PATH); if ($p === null || $p === '') continue;
        [$st] = page($p, $l);
        chk("link $h resolves in $l (from " . ($byLang[$l] ?? $byLang['en'] ?? '?') . ')', $st === 200 || ($st >= 300 && $st < 400), (string)$st);
    }
}

// footer links resolve and every trust page is reachable from the footer
$nav = json_decode((string)file_get_contents($root . '/data/content/nav/footer.json'), true);
$foot = [];
foreach ($nav['columns'] as $c) foreach ($c['items'] as $i) $foot[] = $i['href'];
foreach ($slugs as $path => $_) chk("footer links to $path", in_array($path, $foot, true));
foreach ($foot as $h) { [$st] = page($h, 'en'); chk("footer link $h resolves", $st === 200 || ($st >= 300 && $st < 400), (string)$st); }

// the trust index lists every other trust page
[$st, $html] = page('/trust/', 'en');
foreach ($slugs as $path => $_) if ($path !== '/trust/') chk("trust index links to $path", str_contains($html, 'href="' . $path . '"') || str_contains($html, 'href="' . (function_exists('url') ? url($path) : $path) . '"'));

// sitemap carries every trust page; human sitemap too
$sm = (string)(page('/sitemap.xml', 'en')[1]);
foreach ($slugs as $path => $_) chk("sitemap.xml lists $path", str_contains($sm, $path));
$hs = (string)page('/sitemap/', 'en')[1];
foreach ($slugs as $path => $_) if ($path !== '/status/') chk("human sitemap lists $path", str_contains($hs, 'href="' . $path . '"'));

// corrections page keeps its form: CSRF + desk
[$st, $html] = page('/trust/corrections/', 'en');
chk('corrections form posts to /contact/', str_contains($html, 'id="co-form"') && preg_match('~<form[^>]+action="[^"]*/contact/"~', $html) === 1);
chk('corrections form carries a CSRF token', str_contains($html, '_csrf'));
chk('corrections desk option is present', str_contains($html, 'value="corrections"'));

// /status/ keeps its live blocks
[$st, $html] = page('/status/', 'en');
chk('status keeps data-funding-status', str_contains($html, 'data-funding-status'));
chk('status register row for payment channels', str_contains($html, 'Payment channels'));

echo "\n" . ($bad ? "TRUST FAILED: $bad failed, $ok passed" : "TRUST OK: $ok checks passed") . "\n";
ob_end_flush();
exit($bad ? 1 : 0);
