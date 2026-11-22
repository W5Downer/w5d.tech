<?php
declare(strict_types=1);

namespace W5D;

/**
 * Third-party integrations (config/integrations.php), configured by the owner in the portal.
 *  - tracking (GA4, GTM, Google Ads, Meta Pixel): loaded by js/integrations.js ONLY after the visitor accepts the cookie banner;
 *    never on the owner portal, the account area, payment pages, the unsubscribe page or the public API.
 *  - reCAPTCHA: server-side verification on every public form (Forms::guard / Accounts::guard / lookup / optional admin login / optional API);
 *    the secret never leaves the server.
 *  - Content-Security-Policy: each integration's domains are added only while it is active, and only on the paths where it can run.
 */
final class Integrations
{
    private const PREFIX = 'int';
    public static string $last = '';
    private static string $path = '/';

    /** Called once per request by App::handle (path without APP_BASE_PATH) so the CSP header and the page markup always agree. */
    public static function setPath(string $p): void { self::$path = $p; }
    public static function path(): string { return self::$path; }

    private static function adminPath(): string { return '/' . trim((string)Env::get('ADMIN_URL_PATH', 'panel-x9z'), '/'); }

    public static function all(): array { static $d = null; return $d ??= require ROOT . '/config/integrations.php'; }

    public static function cfg(string $id, string $k): string { try { return SettingsForm::cfg(self::all(), self::PREFIX, $id, $k); } catch (\Throwable) { return ''; } }
    public static function on(string $id): bool { try { return SettingsForm::on(self::all(), self::PREFIX, $id); } catch (\Throwable) { return false; } }
    public static function ready(string $id): bool { try { return SettingsForm::ready(self::all(), self::PREFIX, $id); } catch (\Throwable) { return false; } }

    /* ------------------------------------------------------------ where tracking may run */

    /** Paths that never carry third-party trackers (private, signed or secret-bearing pages). */
    public static function trackPath(string $path): bool
    {
        $admin = self::adminPath();
        if ($path === $admin || str_starts_with($path, $admin . '/')) return false;
        return !preg_match('#^/(account|pay|unsubscribe|subscription/manage|certificate|language|lang|api|_)(/|$)#', $path);
    }

    /** Tracker configuration for this path, or null when nothing applies. Contains no secrets. */
    public static function tracking(string $path): ?array
    {
        if (!self::trackPath($path)) return null;
        $t = [];
        $sig = false;
        $done = (bool)preg_match('#^/checkout/done/#', $path); // URL carries an order reference: Meta cannot hide it, GA/Ads get a sanitised location
        if (self::ready('ga4')) { $t['ga4'] = self::cfg('ga4', 'measurement_id'); $sig = $sig || self::cfg('ga4', 'signals') === '1'; }
        if (self::ready('gtm')) $t['gtm'] = self::cfg('gtm', 'container_id');
        if (self::ready('gads')) { $t['ads'] = ['id' => self::cfg('gads', 'conversion_id'), 'label' => self::cfg('gads', 'conversion_label')]; $sig = $sig || self::cfg('gads', 'signals') === '1'; }
        if (self::ready('meta') && !$done) $t['meta'] = self::cfg('meta', 'pixel_id');
        if (!$t) return null;
        $t['sig'] = $sig;
        $t['conv'] = (bool)preg_match('#^/(join/thanks|checkout/done/[A-Za-z0-9\-]+)/?$#', $path);
        if ($done) $t['loc'] = url('/checkout/done/');
        return $t;
    }

    /* ------------------------------------------------------------ reCAPTCHA */

    public static function captchaReady(): bool { return self::ready('recaptcha'); }
    public static function captchaAdmin(): bool { return self::captchaReady() && self::cfg('recaptcha', 'admin_login') === '1'; }

    /** reCAPTCHA is loaded on public pages, and on the portal only when the owner protects its sign-in; never on the JSON API. */
    public static function captchaHere(string $path): bool
    {
        if (!self::captchaReady() || str_starts_with($path, '/api/')) return false;
        $a = self::adminPath();
        return !($path === $a || str_starts_with($path, $a . '/')) || self::captchaAdmin();
    }

    /** Public, non-secret captcha configuration for js/integrations.js, or null. */
    public static function captchaPublic(): ?array
    {
        if (!self::captchaReady()) return null;
        return ['v' => (int)self::cfg('recaptcha', 'version'), 'key' => self::cfg('recaptcha', 'site_key'), 'domain' => self::cfg('recaptcha', 'domain')];
    }

    /** What js/integrations.js needs on this path (null = emit nothing). */
    public static function pageConfig(?string $path = null): ?array
    {
        $path ??= self::$path;
        $out = [];
        if ($t = self::tracking($path)) $out['track'] = $t;
        if (self::captchaHere($path) && ($c = self::captchaPublic())) $out['captcha'] = $c;
        return $out ?: null;
    }

    /** Markup placed next to the honeypot of every public form ('' when reCAPTCHA is off). */
    public static function field(bool $admin = false): string
    {
        if (!self::captchaReady() || ($admin && !self::captchaAdmin())) return '';
        if ((int)self::cfg('recaptcha', 'version') === 2) return '<div class="w5-captcha my-3" data-captcha-v2></div><p class="text-xs text-red-500" role="alert" hidden data-captcha-msg>' . e(__('captcha.required')) . '</p>';
        return '<input type="hidden" name="g-recaptcha-response" value="" data-captcha autocomplete="off">'
            . '<p class="text-xs text-slate-500 dark:text-slate-400 mt-2">' . rich(__('captcha.notice')) . '</p>';
    }

    public static function message(): array
    {
        if (self::$last === 'outage') return ['en' => 'The verification service is unreachable. Please try again in a minute.', 'fr' => 'Le service de vérification est injoignable. Réessayez dans une minute.', 'ar' => 'خدمة التحقق غير متاحة. حاول مرة أخرى بعد دقيقة.'];
        return ['en' => 'Please confirm you are not a robot, then send the form again.', 'fr' => 'Veuillez confirmer que vous n’êtes pas un robot, puis renvoyez le formulaire.', 'ar' => 'يرجى تأكيد أنك لست روبوتا ثم أعد إرسال النموذج.'];
    }

    /**
     * Server-side verification. Returns null when the request may proceed (captcha off, not required here, or passed),
     * otherwise a short reason (missing|invalid|low|outage) and sets self::$last. Never throws.
     * $scope: form (default) | api | admin.
     */
    public static function captcha(Request $q, string $scope = 'form'): ?string
    {
        self::$last = '';
        if (!self::captchaReady()) return null;
        if ($scope === 'api' && self::cfg('recaptcha', 'api') !== '1') return null;
        if ($scope === 'admin' && !self::captchaAdmin()) return null;
        $tok = $q->post['g-recaptcha-response'] ?? ($_SERVER['HTTP_X_RECAPTCHA_TOKEN'] ?? '');
        $tok = is_string($tok) ? trim($tok) : '';
        if ($tok === '') return self::fail('missing');
        if (strlen($tok) > 4096 || !preg_match('/^[A-Za-z0-9_\-\.]+$/', $tok)) return self::fail('invalid');
        $secret = self::cfg('recaptcha', 'secret_key');
        $dom = self::cfg('recaptcha', 'domain');
        $url = self::cfg('recaptcha', 'verify_url') ?: 'https://' . $dom . '/recaptcha/api/siteverify';
        $r = Http::request('POST', $url, [], null, ['secret' => $secret, 'response' => $tok, 'remoteip' => $q->ip()], 6);
        $j = $r['json'];
        $codes = is_array($j) ? array_map('strval', (array)($j['error-codes'] ?? [])) : [];
        $misconfigured = (bool)array_intersect($codes, ['missing-input-secret', 'invalid-input-secret', 'bad-request']);
        if ($r['error'] !== null || $r['status'] !== 200 || !is_array($j) || $misconfigured) {
            self::log('outage', $r['status'] . ' ' . implode(',', $codes) . ' ' . (string)$r['error']);
            return self::cfg('recaptcha', 'on_outage') === 'closed' ? self::fail('outage') : null;
        }
        if (empty($j['success'])) return self::fail('invalid', implode(',', $codes));
        if ((int)self::cfg('recaptcha', 'version') === 3) {
            if (isset($j['action']) && $j['action'] !== 'w5d_form') return self::fail('invalid', 'action');
            $score = (float)($j['score'] ?? 0);
            if ($score * 100 < (int)self::cfg('recaptcha', 'threshold')) return self::fail('low', (string)$score);
        }
        return null;
    }

    private static function fail(string $why, string $detail = ''): string
    {
        self::$last = $why;
        self::log($why, $detail);
        return $why;
    }

    private static function log(string $why, string $detail): void
    {
        $dir = ROOT . '/storage/logs';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        @file_put_contents($dir . '/captcha-' . date('Y-m-d') . '.log', '[' . date('c') . '] ' . $why . ($detail !== '' ? ' ' . preg_replace('/[^\w ,.\-:]/', '', $detail) : '') . "\n", FILE_APPEND);
    }

    /* ------------------------------------------------------------ webmaster verification tags */

    /** @return array<string,string> meta name => content */
    public static function verificationTags(): array
    {
        if (!self::ready('webmaster')) return [];
        $out = [];
        foreach (['google' => 'google-site-verification', 'bing' => 'msvalidate.01', 'facebook' => 'facebook-domain-verification'] as $k => $name) {
            $v = self::cfg('webmaster', $k);
            if ($v !== '') $out[$name] = $v;
        }
        return $out;
    }

    /* ------------------------------------------------------------ Content-Security-Policy */

    public static function csp(string $path): string
    {
        $script = ["'self'"]; $connect = ["'self'"]; $img = ["'self'", 'data:']; $frame = [];
        try {
            if ($t = self::tracking($path)) {
                if (isset($t['ga4']) || isset($t['ads'])) {
                    $script[] = 'https://www.googletagmanager.com';
                    array_push($connect, 'https://www.google-analytics.com', 'https://*.google-analytics.com', 'https://*.analytics.google.com', 'https://www.googletagmanager.com', 'https://stats.g.doubleclick.net');
                    array_push($img, 'https://www.google-analytics.com', 'https://www.googletagmanager.com');
                }
                if (isset($t['gtm'])) {
                    $script[] = 'https://www.googletagmanager.com';
                    array_push($connect, 'https://www.googletagmanager.com', 'https://www.google-analytics.com', 'https://*.google-analytics.com');
                    array_push($img, 'https://www.googletagmanager.com', 'https://www.google-analytics.com');
                    foreach (preg_split('/\s+/', self::cfg('gtm', 'extra_origins'), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $o) {
                        if (preg_match('#^https://[A-Za-z0-9.\-*]+(:\d+)?$#', $o)) { $script[] = $o; $connect[] = $o; $img[] = $o; }
                    }
                }
                if (isset($t['ads'])) {
                    array_push($script, 'https://www.googleadservices.com', 'https://googleads.g.doubleclick.net');
                    array_push($connect, 'https://www.google.com', 'https://googleads.g.doubleclick.net', 'https://pagead2.googlesyndication.com');
                    array_push($img, 'https://googleads.g.doubleclick.net', 'https://www.google.com', 'https://www.googleadservices.com');
                }
                if (isset($t['meta'])) {
                    $script[] = 'https://connect.facebook.net';
                    array_push($connect, 'https://www.facebook.com', 'https://connect.facebook.net');
                    $img[] = 'https://www.facebook.com';
                }
            }
            if (self::captchaHere($path)) {
                $d = 'https://' . self::cfg('recaptcha', 'domain');
                array_push($script, $d, 'https://www.gstatic.com');
                array_push($connect, $d);
                $frame[] = $d;
            }
        } catch (\Throwable) {}
        $u = fn(array $a) => implode(' ', array_values(array_unique($a)));
        return "default-src 'self'; img-src " . $u($img) . "; style-src 'self' 'unsafe-inline'; script-src " . $u($script) . "; font-src 'self'; connect-src " . $u($connect)
            . ($frame ? '; frame-src ' . $u(array_merge(["'self'"], $frame)) : '') . "; frame-ancestors 'self'; base-uri 'self'; form-action 'self'";
    }
}
