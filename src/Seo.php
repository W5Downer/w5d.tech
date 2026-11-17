<?php
declare(strict_types=1);

namespace W5D;

/** Head metadata: canonical, hreflang, Open Graph, Twitter cards and JSON-LD (Organization, WebSite, WebPage, BreadcrumbList, FAQPage, Offer). */
final class Seo
{
    /** Set by controllers (Pages::render) so the layout can describe the page. */
    private static array $page = [];
    public static function page(array $p): void { self::$page = $p; }

    public static function orgName(): string { return (string)Env::get('SEO_ORG_NAME', 'W5D TECH'); }

    private static function plain(string $s): string { return trim(preg_replace('/\s+/u', ' ', strip_tags(str_replace(['**', '`'], '', $s)))); }

    /** Breadcrumb trail [[label, path], ...] derived from the canonical path. */
    public static function crumbs(string $path): array
    {
        $labels = ['macro-works' => __('nav.macro'), 'services' => __('nav.services'), 'projects' => __('nav.projects'), 'investors' => __('nav.investors'), 'pricing' => __('nav.pricing'),
            'careers' => __('nav.careers'), 'join' => __('nav.join'), 'trust' => __('nav.trust'), 'countries' => __('nav.company'), 'tn' => __('nav.tunisia'), 'for' => __('nav.audiences'), 'account' => __('acct.account')];
        $out = [[__('nav.home'), '/']];
        $acc = '';
        $segs = array_values(array_filter(explode('/', trim($path, '/'))));
        foreach ($segs as $i => $s) {
            $acc .= '/' . $s;
            $last = $i === count($segs) - 1;
            $label = $labels[$s] ?? null;
            if ($label === null || ($last && !isset($labels[$s]))) $label = self::$page['title_short'] ?? ucfirst(str_replace('-', ' ', $s));
            $out[] = [$label, $acc . '/'];
        }
        return $out;
    }

    public static function graph(string $path, string $title, string $desc, string $robots, string $image): array
    {
        $base = Env::baseUrl();
        $url = $base . url($path);
        $org = ['@type' => 'Organization', '@id' => $base . '/#org', 'name' => self::orgName(), 'url' => $base . '/', 'logo' => ['@type' => 'ImageObject', 'url' => abs_url('assets/_base/images/icon-512.png'), 'width' => 512, 'height' => 512],
            'email' => (string)Env::get('SEO_ORG_EMAIL', 'owner@w5d.tech'), 'areaServed' => 'TN', 'knowsLanguage' => ['en', 'fr', 'ar']];
        if ($s = array_values(Social::profiles())) $org['sameAs'] = $s;
        $g = [$org, ['@type' => 'WebSite', '@id' => $base . '/#site', 'url' => $base . '/', 'name' => 'W5D — World Five Dimensions', 'inLanguage' => lang(), 'publisher' => ['@id' => $base . '/#org']]];
        $page = ['@type' => self::$page['ld_type'] ?? 'WebPage', '@id' => $url . '#page', 'url' => $url, 'name' => $title, 'description' => $desc, 'inLanguage' => lang(), 'isPartOf' => ['@id' => $base . '/#site'], 'about' => ['@id' => $base . '/#org'], 'primaryImageOfPage' => ['@type' => 'ImageObject', 'url' => $image], 'dateModified' => (string)Variables::get('dates.as_of')];
        $g[] = $page;
        $crumbs = self::crumbs($path);
        if (count($crumbs) > 1) {
            $g[] = ['@type' => 'BreadcrumbList', '@id' => $url . '#crumbs', 'itemListElement' => array_map(fn($c, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0], 'item' => $base . url($c[1])], $crumbs, array_keys($crumbs))];
        }
        // FAQPage from any faq blocks on the page
        $qa = [];
        foreach (self::$page['sections'] ?? [] as $s) {
            if (($s['type'] ?? '') !== 'faq') continue;
            foreach ($s['items'] ?? [] as $it) {
                $q = self::plain(t($it['q'] ?? '')); $a = self::plain(t($it['a'] ?? ''));
                if ($q !== '' && $a !== '') $qa[] = ['@type' => 'Question', 'name' => $q, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a]];
            }
        }
        if ($qa) $g[] = ['@type' => 'FAQPage', '@id' => $url . '#faq', 'mainEntity' => $qa];
        // Offer: the published daily price of one part (only where the price is the page subject)
        if (in_array($path, ['/investors/', '/pricing/', '/checkout/', '/subscription/'], true)) {
            $g[] = ['@type' => 'Product', '@id' => $base . '/#part', 'name' => 'W5D part', 'description' => 'One part of W5D: an investment in a certified path, not a job. No return is promised.', 'brand' => ['@id' => $base . '/#org'],
                'offers' => ['@type' => 'Offer', 'price' => number_format((float)Variables::get('price.day'), 2, '.', ''), 'priceCurrency' => (string)Variables::get('money.currency', 'TND'), 'availability' => 'https://schema.org/InStock', 'url' => $base . url('/checkout/'), 'areaServed' => 'TN']];
        }
        if (preg_match('#^/services/[^/]+/$#', $path)) {
            $g[] = ['@type' => 'Service', '@id' => $url . '#service', 'name' => $title, 'description' => $desc, 'provider' => ['@id' => $base . '/#org'], 'areaServed' => 'TN', 'serviceType' => 'Certified weekly work package'];
        }
        return $g;
    }

    public static function jsonLd(array $graph): string
    {
        return json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    }
}
