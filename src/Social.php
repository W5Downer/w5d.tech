<?php
declare(strict_types=1);

namespace W5D;

/** Social share configuration (from .env + config/social.php) and Open Graph image aliases. */
final class Social
{
    private const DEFAULT_NETWORKS = 'native,copy,whatsapp,facebook,x,linkedin,telegram,reddit,email';

    public static function cfg(): array { static $c = null; return $c ??= require ROOT . '/config/social.php'; }
    public static function enabled(): bool { return Env::bool('SOCIAL_SHARE_ENABLED', true); }
    public static function via(): string { return ltrim(trim((string)Env::get('SOCIAL_SHARE_VIA', '')), '@'); }
    public static function hashtags(): string { return implode(',', array_filter(array_map(fn($h) => preg_replace('/[^\p{L}\p{N}_]/u', '', $h), explode(',', (string)Env::get('SOCIAL_SHARE_HASHTAGS', ''))))); }

    /** Ordered network ids requested in .env, restricted to the ones we know. */
    public static function ids(): array
    {
        $known = array_merge(['native', 'copy'], array_keys(self::cfg()['networks']));
        $out = [];
        foreach (explode(',', (string)Env::get('SOCIAL_SHARE_NETWORKS', self::DEFAULT_NETWORKS)) as $id) {
            $id = strtolower(trim($id));
            if ($id !== '' && in_array($id, $known, true) && !in_array($id, $out, true)) $out[] = $id;
        }
        return $out;
    }

    /** External share links for a page: id => [label, href]. */
    public static function links(string $url, string $text): array
    {
        $r = ['{url}' => rawurlencode($url), '{text}' => rawurlencode($text), '{hashtags}' => rawurlencode(self::hashtags()), '{via}' => rawurlencode(self::via())];
        $out = [];
        foreach (self::ids() as $id) {
            $n = self::cfg()['networks'][$id] ?? null;
            if (!$n) continue;
            $href = strtr($n['href'], $r);
            $href = preg_replace('/&(hashtags|via)=(?=&|$)/', '', $href);
            $out[$id] = ['label' => $n['label'], 'href' => $href];
        }
        return $out;
    }

    /** Public profile URLs (Organization.sameAs). */
    public static function profiles(): array
    {
        $o = [];
        foreach (['FACEBOOK', 'X', 'LINKEDIN', 'GITHUB', 'INSTAGRAM', 'YOUTUBE'] as $k) {
            $v = trim((string)Env::get('SOCIAL_PROFILE_' . $k, ''));
            if (preg_match('#^https://#', $v)) $o[strtolower($k)] = $v;
        }
        return $o;
    }

    /** Image alias for a canonical path. */
    public static function alias(string $path): string
    {
        $map = self::cfg()['images'];
        if (isset($map[$path])) return $map[$path];
        $f = fn(string $a) => is_file(ROOT . '/htdocs/assets/_base/images/social/' . $a . '.jpg');
        if (preg_match('#^/macro-works/([a-z0-9-]+)/$#', $path, $m) && $f('og-macro-' . $m[1])) return 'og-macro-' . $m[1];
        if (preg_match('#^/for/([a-z]+)/$#', $path, $m) && $f('og-for-' . $m[1])) return 'og-for-' . $m[1];
        $prefix = ['/services/' => 'og-service', '/projects/' => 'og-project', '/tn/' => 'og-tunisia-governorate', '/trust/' => 'og-trust', '/account/' => 'og-account'];
        foreach ($prefix as $p => $a) if (str_starts_with($path, $p) && $f($a)) return $a;
        return pathinfo((string)Env::get('SOCIAL_DEFAULT_IMAGE', 'og-default.jpg'), PATHINFO_FILENAME) ?: 'og-default';
    }

    public static function imageUrl(string $path, ?string $explicit = null): string
    {
        $a = $explicit ?: self::alias($path);
        if (!is_file(ROOT . '/htdocs/assets/_base/images/social/' . $a . '.jpg')) $a = 'og-default';
        return abs_url('assets/_base/images/social/' . $a . '.jpg');
    }
}
