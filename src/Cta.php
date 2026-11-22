<?php
declare(strict_types=1);

namespace W5D;

/**
 * The one primary call-to-action of the chrome (header button, mobile menu button, phone tab bar) for the page being read.
 * W5D serves several doors (learn, lead, hire, refer, support), so the button follows the SUBJECT of the page instead of always saying "invest".
 * Labels are language-file keys (cta.* / ux.tab_*), so every string stays en/fr/ar. Page bodies carry their own CTAs in the content JSON.
 */
final class Cta
{
    /**
     * key => icon, href, label key, tab label key,
     *   paths = pages that BELONG to this door (the phone tab shows as current there),
     *   also  = pages that only borrow this door's button (the tab is not current there).
     */
    private const DOORS = [
        'invest'    => ['icon' => 'coins', 'href' => '/checkout/', 'label' => 'cta.invest', 'tab' => 'ux.tab_invest',
            'paths' => ['/investors', '/pricing', '/checkout', '/subscription', '/invest', '/trust/statement'], 'also' => []],
        'learn'     => ['icon' => 'graduation-cap', 'href' => '/join/eligibility/', 'label' => 'cta.eligibility', 'tab' => 'ux.tab_learn',
            'paths' => ['/join', '/how-it-works', '/certification', '/app', '/for/member', '/for/user'], 'also' => ['/services', '/countries', '/tn']],
        'lead'      => ['icon' => 'users', 'href' => '/careers/managers/', 'label' => 'cta.manager', 'tab' => 'ux.tab_lead',
            'paths' => ['/careers', '/for/leader'], 'also' => []],
        'client'    => ['icon' => 'handshake', 'href' => '/contact/?desk=partners', 'label' => 'cta.partners', 'tab' => 'ux.tab_client',
            'paths' => ['/partners', '/for/customer'], 'also' => []],
        'affiliate' => ['icon' => 'gift', 'href' => '/account/affiliate/', 'label' => 'cta.affiliate', 'tab' => 'ux.tab_refer',
            'paths' => ['/affiliate'], 'also' => []],
        'path'      => ['icon' => 'compass', 'href' => '/for/', 'label' => 'cta.path', 'tab' => 'ux.tab_path',
            'paths' => ['/for'], 'also' => []],
    ];

    private static function hit(string $here, array $prefixes): bool
    {
        foreach ($prefixes as $p) if ($here === $p || str_starts_with($here . '/', $p . '/')) return true;
        return false;
    }

    /** Door key for a canonical path ("path" is the neutral default: it helps a visitor who is not sure where to start). */
    public static function door(string $path): string
    {
        $here = rtrim($path, '/') ?: '/';
        // longest prefix wins, so /for/leader beats /for
        $best = 'path'; $len = -1;
        foreach (self::DOORS as $k => $d) {
            foreach (array_merge($d['paths'], $d['also']) as $p) {
                if (($here === $p || str_starts_with($here . '/', $p . '/')) && strlen($p) > $len) { $best = $k; $len = strlen($p); }
            }
        }
        return $best;
    }

    /** @return array{key:string,icon:string,href:string,label:string,tab:string,current:bool} */
    public static function for(string $path): array
    {
        $here = rtrim($path, '/') ?: '/';
        $k = self::door($path);
        $d = self::DOORS[$k];
        $href = $d['href'];
        // a service page asks for eligibility for THAT service
        if ($k === 'learn' && preg_match('#^/services/([a-z0-9-]+)$#', $here, $m)) $href = '/join/eligibility/?service=' . $m[1];
        return ['key' => $k, 'icon' => $d['icon'], 'href' => $href, 'label' => __($d['label']), 'tab' => __($d['tab']),
            'current' => self::hit($here, $d['paths'])];
    }
}
