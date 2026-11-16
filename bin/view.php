<?php
// php bin/view.php cache   — compile every Blade template ahead of time (like `artisan view:cache`)
// php bin/view.php clear   — delete all compiled views            (like `artisan view:clear`)
// php bin/view.php status  — cache location, size, mode
require __DIR__ . '/_boot.php';
use W5D\ViewCache;

$cmd = $argv[1] ?? 'status';
switch ($cmd) {
    case 'clear':
        echo 'Compiled views cleared: ', ViewCache::clear(), " file(s).\n"; break;
    case 'cache':
        $n = ViewCache::clear();
        $r = ViewCache::warm();
        echo "Cleared $n, compiled {$r['compiled']} template(s).\n";
        foreach ($r['errors'] as $f => $m) echo "  ERROR $f: $m\n";
        exit($r['errors'] ? 1 : 0);
    case 'status':
        $s = ViewCache::stats();
        printf("dir: %s\ncompiled files: %d / %d templates\nsize: %.1f KB\ncache: %s\nsource check (mtime): %s\n",
            $s['dir'], $s['files'], $s['templates'], $s['bytes'] / 1024, $s['enabled'] ? 'on' : 'OFF', $s['check_sources'] ? 'on (dev)' : 'off (trust cache — run `view.php cache` on deploy)');
        break;
    default:
        fwrite(STDERR, "usage: php bin/view.php [cache|clear|status]\n"); exit(2);
}
