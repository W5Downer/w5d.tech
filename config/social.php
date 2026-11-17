<?php
/** Social share networks. Which ones appear, in which order, is decided in .env (SOCIAL_SHARE_NETWORKS); URL templates live here.
 *  Placeholders: {url} {text} {hashtags} {via} (all URL-encoded). "native" (Web Share API) and "copy" are handled in the browser. */
return [
    'networks' => [
        'whatsapp' => ['label' => 'WhatsApp', 'href' => 'https://wa.me/?text={text}%20{url}'],
        'facebook' => ['label' => 'Facebook', 'href' => 'https://www.facebook.com/sharer/sharer.php?u={url}'],
        'x'        => ['label' => 'X', 'href' => 'https://twitter.com/intent/tweet?url={url}&text={text}&hashtags={hashtags}&via={via}'],
        'linkedin' => ['label' => 'LinkedIn', 'href' => 'https://www.linkedin.com/sharing/share-offsite/?url={url}'],
        'telegram' => ['label' => 'Telegram', 'href' => 'https://t.me/share/url?url={url}&text={text}'],
        'reddit'   => ['label' => 'Reddit', 'href' => 'https://www.reddit.com/submit?url={url}&title={text}'],
        'email'    => ['label' => 'E-mail', 'href' => 'mailto:?subject={text}&body={url}'],
    ],
    // exact path => image alias (file: htdocs/assets/_base/images/social/{alias}.jpg). See docs/social-share-image-prompts.md
    'images' => [
        '/' => 'og-home', '/investors/' => 'og-investors', '/pricing/' => 'og-pricing', '/checkout/' => 'og-checkout', '/subscription/' => 'og-subscription',
        '/how-it-works/' => 'og-how-it-works', '/join/' => 'og-join', '/join/eligibility/' => 'og-join-eligibility', '/app/register/' => 'og-app-register',
        '/careers/' => 'og-careers', '/careers/managers/' => 'og-careers-managers', '/partners/' => 'og-partners', '/about/' => 'og-about',
        '/ontology/' => 'og-ontology', '/countries/tunisia/' => 'og-countries-tunisia', '/faq/' => 'og-faq', '/contact/' => 'og-contact',
        '/newsletter/' => 'og-newsletter', '/status/' => 'og-status', '/macro-works/' => 'og-macro-works', '/services/' => 'og-services',
        '/projects/' => 'og-projects', '/developers/' => 'og-developers', '/agents/' => 'og-agents', '/sitemap/' => 'og-sitemap', '/for/' => 'og-for',
    ],
];
