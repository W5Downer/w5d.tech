<?php
declare(strict_types=1);
// Unit tests for W5D\ViewCache (no server needed).  Usage: php tests/viewcache.php
require __DIR__ . '/../bin/_boot.php';

use W5D\ViewCache;

$ok = $bad = 0;
function check(string $name, bool $cond, string $info = ''): void
{
    global $ok, $bad;
    if ($cond) { $ok++; echo "  ok   $name\n"; } else { $bad++; echo "  FAIL $name  $info\n"; }
}
function setEnv(?string $cache, ?string $check, ?string $appEnv = null): void
{
    // Env::get() prefers real env vars over the .env file, so "unset" would let the file leak in (e.g. a production
    // .env with VIEW_CACHE_CHECK=false). Always set explicit values: '' means "auto" for CHECK, 'true' is the cache default.
    putenv('VIEW_CACHE=' . ($cache ?? 'true'));
    putenv('VIEW_CACHE_CHECK=' . ($check ?? ''));
    putenv('APP_ENV=' . ($appEnv ?? 'local'));
    ViewCache::clear(); // also resets the per-request memo
}

$hadWarmCache = count(glob(ViewCache::dir() . '/*.php') ?: []) > 0; // restored at the end (this suite clears the cache)
$src = sys_get_temp_dir() . '/w5d-vc-test-' . bin2hex(random_bytes(4)) . '.blade.php';
file_put_contents($src, "<p>v1 {{ \$x }}</p>\n");
$compiledPath = ViewCache::pathFor($src);
register_shutdown_function(static function () use ($src, $compiledPath) { @unlink($src); @unlink($compiledPath); ViewCache::clear(); });

function renderSrc(string $src): string
{
    $env = new W5D\ViewEnv();
    return trim((new W5D\View($env, $src, ['x' => 'A']))->render());
}

echo "naming\n";
check('path is inside storage/framework/views', str_starts_with($compiledPath, ROOT . '/storage/framework/views/'));
check('file name = sha1(source|COMPILER).php', basename($compiledPath) === sha1($src . '|' . ViewCache::COMPILER) . '.php');
check('different sources map to different files', ViewCache::pathFor($src . 'x') !== $compiledPath);

echo "mode: local default (cache on, mtime check on)\n";
setEnv(null, null, 'local');
check('cache enabled by default', ViewCache::enabled());
check('source check on outside production', ViewCache::checkSources());
check('expired when not compiled yet', ViewCache::isExpired($src));
check('renders v1', renderSrc($src) === '<p>v1 A</p>', renderSrc($src));
check('compiled file written', is_file($compiledPath));
check('compiled file records its source', str_contains((string)file_get_contents($compiledPath), 'PATH ' . $src . ' ENDPATH'));
touch($src, time() - 60); // source clearly older than the compiled file
check('not expired when the source is older than the compiled file', !ViewCache::isExpired($src));
touch($src, (int)filemtime($compiledPath)); // same mtime => treated as expired (safe tie-break, same as Laravel's >=)
check('equal mtimes count as expired (never serve a possibly stale view)', ViewCache::isExpired($src));
file_put_contents($src, "<p>v2 {{ \$x }}</p>\n");
touch($src, time() + 5); // source strictly newer than compiled
ViewCache::clear(); // fresh request: drop memo + files
check('after clear the file is gone', !is_file($compiledPath));
check('renders v2 after edit', renderSrc($src) === '<p>v2 A</p>');
touch($src, time() + 10);
check('newer source marks cache expired (dev)', ViewCache::isExpired($src));

echo "mode: production default (trust cache)\n";
setEnv(null, null, 'production');
check('source check OFF in production by default', !ViewCache::checkSources());
touch($src, time() - 100);
renderSrc($src);
file_put_contents($src, "<p>v3 {{ \$x }}</p>\n");
touch($src, time() + 20);
ViewCache::clear(); // clear then compile v3 first to prove stale behaviour below
file_put_contents($src, "<p>v2b {{ \$x }}</p>\n");
touch($src, time() - 50);
renderSrc($src); // compiled = v2b
file_put_contents($src, "<p>v4 {{ \$x }}</p>\n");
touch($src, time() + 30);
// new "request": memo reset but keep the file on disk
$r = new ReflectionProperty(ViewCache::class, 'memo'); $r->setAccessible(true); $r->setValue(null, []);
check('trusted cache serves the stale view (documented behaviour)', renderSrc($src) === '<p>v2b A</p>', renderSrc($src));
check('isExpired false even though the source is newer', !ViewCache::isExpired($src));
ViewCache::clear();
check('after `clear` the new source is compiled', renderSrc($src) === '<p>v4 A</p>');

echo "mode: explicit VIEW_CACHE_CHECK overrides\n";
setEnv(null, 'true', 'production');
check('VIEW_CACHE_CHECK=true forces checking in production', ViewCache::checkSources());
setEnv(null, 'false', 'local');
check('VIEW_CACHE_CHECK=false disables checking in local', !ViewCache::checkSources());

echo "mode: VIEW_CACHE=false (in-memory, nothing on disk)\n";
setEnv('false', null, 'local');
check('cache reported disabled', !ViewCache::enabled());
renderSrc($src);
check('no compiled file written to storage/framework/views', !is_file($compiledPath));

echo "warm / clear / stats\n";
setEnv(null, null, 'local');
$before = glob(ViewCache::dir() . '/*.php') ?: [];
$w = ViewCache::warm();
check('warm compiles every template without error', $w['errors'] === [] && $w['compiled'] === count(ViewCache::sources()), json_encode($w['errors']));
$st = ViewCache::stats();
check('stats count matches templates', $st['files'] >= $st['templates'] && $st['templates'] > 50, json_encode($st));
check('sources() excludes views/static/', !array_filter(ViewCache::sources(), fn($p) => str_contains($p, '/views/static/')));
$n = ViewCache::clear();
check('clear removes the compiled files', $n >= $st['templates'] && (glob(ViewCache::dir() . '/*.php') ?: []) === [], "removed $n");

echo "write robustness\n";
$bad2 = sys_get_temp_dir() . '/w5d-vc-bad-' . bin2hex(random_bytes(4)) . '.blade.php';
file_put_contents($bad2, "<p>{{ \$x }}</p>\n");
ViewCache::compiled($bad2);
check('atomic write leaves no .tmp files behind', !glob(ViewCache::dir() . '/*.tmp'));
@unlink($bad2); @unlink(ViewCache::pathFor($bad2));

putenv('VIEW_CACHE'); putenv('VIEW_CACHE_CHECK'); putenv('APP_ENV');
if ($hadWarmCache) { ViewCache::warm(); echo "  (warm view cache restored)\n"; }
echo "\nviewcache: $ok passed, $bad failed\n";
exit($bad ? 1 : 0);
