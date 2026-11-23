<?php
declare(strict_types=1);

namespace W5D;

/**
 * Short paths: the handful of destinations that cover almost every visit, plus a flat search index of the site.
 * One source for the home "choose your path" block, the quick-find dialog, the mobile bar and the 404 page.
 * Labels are language-file keys (ux.path_*), so every string stays in en/fr/ar.
 */
final class QuickPaths
{
    /** @return list<array{key:string,icon:string,href:string,label:string,desc:string}> */
    public static function paths(): array
    {
        $acct = Account::signedIn() ? (Account::check() ? '/account/' : admin_url()) : '/account/login/';
        $defs = [
            ['invest', 'coins', '/checkout/'],
            ['learn', 'graduation-cap', '/join/eligibility/'],
            ['manage', 'users', '/careers/managers/'],
            ['client', 'briefcase', '/partners/'],
            ['status', 'activity', '/status/'],
            ['help', 'life-buoy', '/faq/'],
            ['account', 'circle-user', $acct],
        ];
        $out = [];
        foreach ($defs as [$k, $icon, $href]) {
            $out[] = ['key' => $k, 'icon' => $icon, 'href' => str_starts_with($href, '/') && !str_starts_with($href, admin_url()) ? url($href) : $href,
                'label' => __('ux.path_' . $k), 'desc' => __('ux.path_' . $k . '_d')];
        }
        return $out;
    }

    /** Flat, de-duplicated list of [label, href, hint] used by the quick-find dialog. */
    public static function index(): array
    {
        $seen = [];
        $rows = [];
        $add = function (string $label, string $href, string $hint = '') use (&$seen, &$rows) {
            $u = rtrim($href, '/');
            if ($label === '' || isset($seen[$u])) return;
            $seen[$u] = true;
            $rows[] = ['label' => $label, 'href' => $href, 'hint' => $hint];
        };
        foreach (self::paths() as $p) $add($p['label'], $p['href'], $p['desc']);
        $foot = Content::get('nav', 'footer')['columns'] ?? [];
        foreach ($foot as $col) foreach ($col['items'] ?? [] as $l) $add(t($l['label']), link_to($l['href']), t($col['heading']));
        foreach (Content::all('macros') as $m) $add(t($m['name']), url('/macro-works/' . $m['slug'] . '/'), t($m['tagline'] ?? []));
        foreach (\W5D\Controllers\Pages::visibleAudiences() as $a) $add(ucfirst($a), url('/for/' . $a . '/'), __('nav.audiences'));
        return $rows;
    }
}
