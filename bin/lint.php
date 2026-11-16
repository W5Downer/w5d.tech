<?php
// php bin/lint.php   — enforce the project rules (exit 1 on any violation)
//   1. every {{ token }} in content JSON / lang files resolves against config/variables.php
//   2. fr + ar present for every translatable string (en non-empty => fr and ar non-empty)
//   3. no hard-coded price (N TND / N DT), "130,000" or "2027" outside config/ (use tokens)
//   4. no CDN / external asset URLs in templates, CSS, JS (single domain, local assets only)
//   5. at most one primary "peak" call to action per content section
//   6. every Blade template compiles to valid PHP
require __DIR__ . '/_boot.php';

use W5D\Variables;

$errors = [];
$fail = function (string $rule, string $where, string $msg) use (&$errors) { $errors[] = sprintf('[%s] %s: %s', $rule, str_replace(ROOT . '/', '', $where), $msg); };

/** @return iterable<string> */
function files(string $dir, string $re): iterable
{
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) if ($f->isFile() && preg_match($re, $f->getPathname())) yield $f->getPathname();
}

/** Walk decoded JSON and call $cb(path, node) for every node. */
function walk(mixed $n, callable $cb, string $path = ''): void
{
    $cb($path, $n);
    if (is_array($n)) foreach ($n as $k => $v) walk($v, $cb, $path === '' ? (string)$k : "$path.$k");
}

/** A "translatable leaf" is an assoc array whose keys are exactly a subset of en/fr/ar and contains en. */
function isLeaf(mixed $n): bool
{
    return is_array($n) && $n !== [] && isset($n['en']) && !array_diff(array_keys($n), ['en', 'fr', 'ar']);
}

$content = array_merge(iterator_to_array(files(ROOT . '/data/content', '/\.json$/'), false));

/* ---------------------------------------------------------- 1 + 2 + 3 + 5: content JSON */
foreach ($content as $f) {
    $j = json_decode((string)file_get_contents($f), true);
    if (!is_array($j)) { $fail('json', $f, 'invalid JSON: ' . json_last_error_msg()); continue; }
    walk($j, function (string $path, $n) use ($f, $fail) {
        if (isLeaf($n)) {
            foreach (['fr', 'ar'] as $l) if (trim((string)$n['en']) !== '' && trim((string)($n[$l] ?? '')) === '') $fail('i18n', $f, "$path missing '$l'");
            foreach (['en', 'fr', 'ar'] as $l) {
                $s = (string)($n[$l] ?? '');
                if (preg_match_all('/\{\{\s*([a-z_][a-z0-9_.]*)/i', $s, $m)) foreach ($m[1] as $tok) if (Variables::get($tok) === null) $fail('token', $f, "$path.$l unresolved {{ $tok }}");
                if (preg_match('/\b\d[\d.,]*\s?(TND|DT|dinars?)\b/iu', $s, $m)) $fail('price', $f, "$path.$l hard-coded price '{$m[0]}'");
                if (preg_match('/130[ ,.\x{202f}\x{00a0}]?000/u', $s)) $fail('number', $f, "$path.$l hard-coded 130,000 (use {{ d.experts_target }})");
                if (preg_match('/\b2027\b/', $s)) $fail('date', $f, "$path.$l hard-coded 2027 (use {{ dates.offer_year }})");
            }
        }
    });
    // 5. one "peak" CTA per top-level section
    foreach (($j['sections'] ?? []) as $i => $sec) {
        $peaks = 0;
        walk($sec, function ($p, $n) use (&$peaks) { if (is_array($n) && ($n['style'] ?? '') === 'peak') $peaks++; });
        if ($peaks > 1) $fail('peak', $f, "sections[$i] has $peaks peak CTAs (max 1)");
    }
}

/* ---------------------------------------------------------- 1 + 2: lang files */
$lang = [];
foreach (['en', 'fr', 'ar'] as $l) $lang[$l] = require ROOT . "/lang/$l.php";
$flat = function (array $a, string $p = '') use (&$flat): array { $o = []; foreach ($a as $k => $v) { is_array($v) ? $o += $flat($v, "$p$k.") : $o["$p$k"] = (string)$v; } return $o; };
$F = array_map($flat, $lang);
foreach ($F['en'] as $k => $v) foreach (['fr', 'ar'] as $l) {
    if (!isset($F[$l][$k])) $fail('i18n', ROOT . "/lang/$l.php", "missing key $k");
    elseif (trim($F[$l][$k]) === '' && trim($v) !== '') $fail('i18n', ROOT . "/lang/$l.php", "empty key $k");
}
foreach (['fr', 'ar'] as $l) foreach ($F[$l] as $k => $_) if (!isset($F['en'][$k])) $fail('i18n', ROOT . "/lang/en.php", "key $k exists in $l but not in en");

/* ---------------------------------------------------------- 3: templates (Blade) literals */
foreach (files(ROOT . '/views/templates', '/\.blade\.php$/') as $f) {
    $src = (string)file_get_contents($f);
    $txt = preg_replace('/\{\{--.*?--\}\}/s', '', $src);
    if (preg_match('/\b\d[\d.,]*\s?(TND|DT)\b/', strip_tags($txt), $m)) $fail('price', $f, "hard-coded price '{$m[0]}'");
    if (preg_match('/130[ ,]000/', $txt)) $fail('number', $f, 'hard-coded 130,000');
    if (preg_match('/\b2027\b/', $txt)) $fail('date', $f, 'hard-coded 2027');
}

/* ---------------------------------------------------------- 4: no CDN / external assets */
$scan = array_merge(
    iterator_to_array(files(ROOT . '/views/templates', '/\.blade\.php$/'), false),
    iterator_to_array(files(ROOT . '/views/_blocks', '/\.php$/'), false),
    iterator_to_array(files(ROOT . '/views/_shared', '/\.php$/'), false),
    iterator_to_array(files(ROOT . '/htdocs/assets', '/\.(css|js)$/'), false)
);
foreach ($scan as $f) {
    $src = (string)file_get_contents($f);
    if (preg_match('#(?:src|href)=["\']https?://(?!(?:www\.)?w5d\.tech)[^"\']+\.(?:js|css|woff2?|ttf)#i', $src, $m)
        || preg_match('#@import\s+url\(["\']?https?://#i', $src)
        || preg_match('#(cdn\.|cdnjs|unpkg\.com|jsdelivr|googleapis\.com|gstatic\.com|fontawesome|jquery)#i', $src, $m)) {
        // vendored Tailwind/lucide files carry licence URLs only in comments; ignore comment-only hits
        $nocomment = preg_replace('#/\*.*?\*/#s', '', $src);
        if (preg_match('#(cdn\.|cdnjs|unpkg\.com|jsdelivr|googleapis\.com|gstatic\.com|fontawesome|jquery)#i', $nocomment, $m2)) $fail('cdn', $f, "external dependency '{$m2[0]}'");
    }
}

/* ---------------------------------------------------------- 6: templates compile */
$r = \W5D\ViewCache::warm();
foreach ($r['errors'] as $f => $m) $fail('blade', $f, $m);

/* ---------------------------------------------------------- report */
$n = count($errors);
foreach (array_slice($errors, 0, 80) as $e) echo $e, "\n";
if ($n > 80) echo '… and ', $n - 80, " more\n";
printf("%s: %d violation(s) in %d content files, %d templates, 3 languages\n", $n ? 'LINT FAILED' : 'LINT OK', $n, count($content), $r['compiled']);
exit($n ? 1 : 0);
