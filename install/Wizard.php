<?php
declare(strict_types=1);

namespace W5D\Install;

use W5D\{Request, Response, Router, Csrf, RateLimit, Env, Lang, InstallGate, SysCheck, Cron};

if (!defined('ROOT')) { http_response_code(404); exit; }

/**
 * Browser installer: /install/{step}/. Exists only while the site is not installed (see InstallGate) and lives in the install/
 * folder, which the owner deletes at the end. Standalone page (no database, no site layout, no JavaScript).
 * State is kept in the PHP session: values per .env key; the owner password only as a hash.
 */
final class Wizard
{
    public static function routes(Router $r): void
    {
        $r->any('/install', fn() => Response::redirect(url('/install/requirements/')));
        $r->post('/install/delete', [self::class, 'delete']);
        $r->any('/install/{step}', [self::class, 'step']);
    }

    /* ------------------------------------------------------------------ helpers */

    private static function st(): array { return $_SESSION['inst'] ??= ['v' => [], 'ok' => []]; }

    private static function next(string $step): string
    {
        $o = Fields::ORDER;
        return $o[array_search($step, $o, true) + 1] ?? 'done';
    }

    private static function prev(string $step): ?string
    {
        $o = Fields::ORDER;
        $i = array_search($step, $o, true);
        return $i > 0 ? $o[$i - 1] : null;
    }

    private static function defaults(): array
    {
        $host = (string)preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? ''));
        $base = Env::baseUrl();
        if (str_starts_with($base, 'http://') && !preg_match('#^http://(localhost|127\.0\.0\.1)#', $base)) $base = 'https://' . substr($base, 7);
        $dom = preg_replace('/^www\./', '', $host);
        $st = &$_SESSION['inst'];
        $st['mount'] ??= 'p-' . bin2hex(random_bytes(6));
        return [
            'APP_URL' => $base, 'APP_BASE_PATH' => '', 'APP_LANG' => Lang::current(), 'ADMIN_URL_PATH' => $st['mount'],
            'OWNER_EMAIL' => $dom !== '' && $dom !== 'localhost' ? 'owner@' . $dom : '', 'STAFF_EMAIL_DOMAIN' => '',
            'STAFF_CODE_TTL_MIN' => '10', 'STAFF_IDLE_MINUTES' => '30', 'DB_PATH' => 'storage/w5d.sqlite',
            'BACKUP_DRIVER' => 'external', 'ARCHIVE_DRIVER' => 'prune', 'UPLOAD_MAX_MB' => '2',
            'MAIL_DRIVER' => 'smtp', 'MAIL_PORT' => '587', 'MAIL_ENCRYPTION' => 'tls',
            'PAYMENT_D17_ENABLED' => 'false', 'CRON_MODE' => 'system',
        ];
    }

    private static function clean(string $s, int $max = 200): string
    {
        return mb_substr(trim((string)preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $s)), 0, $max);
    }

    private static function msg(string $k): string
    {
        return match ($k) {
            'req' => tr3('This field is required.', 'Ce champ est obligatoire.', 'هذا الحقل مطلوب.'),
            'url' => tr3('Use the https address of the site without a path, for example https://example.org (plain http only for localhost).', 'Utilisez l\'adresse https du site sans chemin, par exemple https://example.org (http simple seulement pour localhost).', 'استعمل العنوان الآمن للموقع بلا مسار، مثل https://example.org (العنوان غير الآمن مقبول مع localhost فقط).'),
            'base' => tr3('Start with a slash and use letters, digits, - or _, for example /shop, with no trailing slash.', 'Commencez par une barre oblique et utilisez lettres, chiffres, - ou _, par exemple /shop, sans barre finale.', 'ابدأ بشرطة مائلة واستعمل حروفا وأرقاما أو - أو _، مثل /shop، بلا شرطة مائلة في النهاية.'),
            'mount' => tr3('Use 6 to 60 letters, digits, - or _. Names such as admin, panel or dashboard, and the names of public pages, are refused.', 'Utilisez 6 à 60 lettres, chiffres, - ou _. Les noms comme admin, panel ou dashboard, et ceux des pages publiques, sont refusés.', 'استعمل من 6 إلى 60 حرفا أو رقما أو - أو _. تُرفض أسماء مثل admin و panel و dashboard وأسماء الصفحات العامة.'),
            'email' => tr3('Enter a valid e-mail address.', 'Saisissez une adresse e-mail valide.', 'أدخل عنوان بريد صالحا.'),
            'domain' => tr3('Enter a domain such as example.org (no @, no path).', 'Saisissez un domaine tel que example.org (sans @, sans chemin).', 'أدخل نطاقا مثل example.org (بلا @ وبلا مسار).'),
            'ownerdom' => tr3('The owner e-mail must be on the team domain.', 'L\'e-mail du propriétaire doit être sur le domaine de l\'équipe.', 'يجب أن يكون بريد المالك على نطاق الفريق.'),
            'pwshort' => tr3('The password needs at least 12 characters.', 'Le mot de passe doit avoir au moins 12 caractères.', 'تحتاج كلمة المرور إلى 12 حرفا على الأقل.'),
            'pwdiff' => tr3('The two passwords are different.', 'Les deux mots de passe sont différents.', 'كلمتا المرور مختلفتان.'),
            'pwlog' => tr3('With mail set to "log" nothing is e-mailed, so an owner password is required: go back to the owner step and set one.', 'Avec l\'envoi réglé sur « log », rien n\'est envoyé : un mot de passe propriétaire est donc obligatoire. Revenez à l\'étape du propriétaire et définissez-en un.', 'مع ضبط الإرسال على "log" لا يُرسل شيء بالبريد، فكلمة مرور المالك مطلوبة: ارجع إلى خطوة المالك واضبط واحدة.'),
            'num' => tr3('Enter a whole number from :min to :max.', 'Saisissez un nombre entier de :min à :max.', 'أدخل عددا صحيحا من :min إلى :max.'),
            'opt' => tr3('Choose one of the offered values.', 'Choisissez l\'une des valeurs proposées.', 'اختر إحدى القيم المعروضة.'),
            'dbpath' => tr3('Use a file path made of letters, digits, . _ - and /, without .. and not inside the public htdocs folder.', 'Utilisez un chemin de fichier fait de lettres, chiffres, . _ - et /, sans .. et hors du dossier public htdocs.', 'استعمل مسار ملف من حروف وأرقام و . _ - و /، بلا .. وخارج المجلد العام htdocs.'),
            'host' => tr3('Enter the name of the mail server, for example smtp.example.org.', 'Saisissez le nom du serveur de messagerie, par exemple smtp.example.org.', 'أدخل اسم خادم البريد، مثل smtp.example.org.'),
            'gsm' => tr3('Write the number as +216 followed by 8 digits.', 'Écrivez le numéro sous la forme +216 suivi de 8 chiffres.', 'اكتب الرقم بالشكل +216 ثم 8 أرقام.'),
            'https' => tr3('Use a full https address or leave it empty.', 'Utilisez une adresse https complète ou laissez vide.', 'استعمل عنوان https كاملا أو اتركه فارغا.'),
            'handle' => tr3('Use up to 15 letters, digits or _ , without @.', 'Utilisez jusqu\'à 15 lettres, chiffres ou _ , sans @.', 'استعمل حتى 15 حرفا أو رقما أو _ ، بلا @.'),
            'long' => tr3('Too long.', 'Trop long.', 'طويل جدا.'),
            default => $k,
        };
    }

    /** @return array{0: array<string,string>, 1: array<string,string>} [clean values by env key, errors by field key] */
    private static function validate(string $step, array $in, array $have): array
    {
        $vals = []; $err = [];
        $steps = Fields::steps();
        foreach ($steps[$step]['fields'] as $f) {
            $k = $f['env'] ?: ($f['name'] ?? '');
            $type = $f['type'];
            if ($type === 'fixed') { $vals[$f['env']] = $f['value']; continue; }
            if ($type === 'checkbox') { $vals[$f['env']] = !empty($in[$k]) ? 'true' : 'false'; continue; }
            $raw = (string)($in[$k] ?? '');
            $v = $type === 'password' ? $raw : self::clean($raw);
            if ($type === 'password') { $vals[$k] = $v; continue; }
            if ($v === '' && !empty($f['req'])) { $err[$k] = self::msg('req'); continue; }
            if ($v === '') { $vals[$k] = ''; continue; }
            if ($type === 'number') {
                if (!preg_match('/^\d{1,5}$/', $v) || (int)$v < $f['min'] || (int)$v > $f['max']) { $err[$k] = str_replace([':min', ':max'], [(string)$f['min'], (string)$f['max']], self::msg('num')); continue; }
                $v = (string)(int)$v;
            }
            if (($type === 'select' || $type === 'radio') && !isset($f['options'][$v])) { $err[$k] = self::msg('opt'); continue; }
            $vals[$k] = $v;
        }
        $e = function (string $k, string $m) use (&$err): void { $err[$k] ??= self::msg($m); }; // by reference: an arrow fn would change a copy
        foreach ($vals as $k => &$v) {
            if (isset($err[$k]) || $v === '') continue;
            switch ($k) {
                case 'APP_URL':
                    $v = rtrim(strtolower($v), '/');
                    if (!preg_match('#^https://[a-z0-9.\-]+(:\d+)?$#', $v) && !preg_match('#^http://(localhost|127\.0\.0\.1)(:\d+)?$#', $v)) $e($k, 'url');
                    break;
                case 'APP_BASE_PATH': if (!preg_match('#^/[A-Za-z0-9_\-/]{1,60}$#', $v) || str_ends_with($v, '/')) $e($k, 'base'); break;
                case 'ADMIN_URL_PATH': $v = trim($v, '/'); if (!preg_match('/^[A-Za-z0-9_\-]{6,60}$/', $v) || in_array(strtolower($v), Fields::RESERVED_MOUNT, true)) $e($k, 'mount'); break;
                case 'OWNER_EMAIL': case 'MAIL_FROM_ADDRESS': case 'MAIL_REPLY_TO': case 'CONTACT_EMAIL': case 'SEO_ORG_EMAIL':
                    $v = strtolower($v); if (!filter_var($v, FILTER_VALIDATE_EMAIL)) $e($k, 'email'); break;
                case 'STAFF_EMAIL_DOMAIN': $v = strtolower(ltrim($v, '@')); if (!preg_match('/^[a-z0-9.\-]+\.[a-z]{2,}$/', $v)) $e($k, 'domain'); break;
                case 'DB_PATH': if (!preg_match('#^[A-Za-z0-9_./\-]+$#', $v) || str_contains($v, '..') || str_starts_with($v, 'htdocs/') || str_ends_with($v, '/')) $e($k, 'dbpath'); break;
                case 'MAIL_HOST': if (!preg_match('/^[A-Za-z0-9.\-]+$/', $v)) $e($k, 'host'); break;
                case 'PAYMENT_D17_GSM': if (!preg_match('/^\+216\d{8}$/', $v)) $e($k, 'gsm'); break;
                case 'SOCIAL_SHARE_VIA': $v = ltrim($v, '@'); if (!preg_match('/^[A-Za-z0-9_]{1,15}$/', $v)) $e($k, 'handle'); break;
                default:
                    if (str_starts_with($k, 'SOCIAL_PROFILE_') && !preg_match('#^https://[^\s<>"\']{4,190}$#i', $v)) $e($k, 'https');
            }
        }
        unset($v);

        // cross-field rules of this step
        $all = $vals + $have;
        if ($step === 'team') {
            $owner = (string)($vals['OWNER_EMAIL'] ?? '');
            $dom = (string)($vals['STAFF_EMAIL_DOMAIN'] ?? '') ?: ($owner !== '' ? substr((string)strrchr($owner, '@'), 1) : '');
            if ($owner !== '' && !isset($err['OWNER_EMAIL']) && !isset($err['STAFF_EMAIL_DOMAIN']) && !str_ends_with($owner, '@' . $dom)) $err['OWNER_EMAIL'] = self::msg('ownerdom');
            $pw = (string)($vals['WEBMASTER_PASSWORD_HASH'] ?? ''); $pw2 = (string)($in['password2'] ?? '');
            unset($vals['password2']);
            if ($pw !== '') {
                if (mb_strlen($pw) < 12) $err['WEBMASTER_PASSWORD_HASH'] = self::msg('pwshort');
                elseif ($pw !== $pw2) $err['password2'] = self::msg('pwdiff');
                else $vals['WEBMASTER_PASSWORD_HASH'] = password_hash($pw, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT);
            }
            if (($vals['WEBMASTER_PASSWORD_HASH'] ?? '') === '') unset($vals['WEBMASTER_PASSWORD_HASH']); // blank keeps the one already typed
        }
        if ($step === 'mail') {
            $drv = (string)($vals['MAIL_DRIVER'] ?? '');
            if (($vals['MAIL_PASSWORD'] ?? '') === '') unset($vals['MAIL_PASSWORD']);
            if ($drv === 'smtp' && ($vals['MAIL_HOST'] ?? '') === '') $err['MAIL_HOST'] = self::msg('host');
            if ($drv !== 'log' && ($vals['MAIL_FOOTER_ADDRESS'] ?? '') === '') $err['MAIL_FOOTER_ADDRESS'] = self::msg('req');
            if ($drv === 'smtp' && ($vals['MAIL_PORT'] ?? '') === '') $err['MAIL_PORT'] = self::msg('req');
        }
        if ($step === 'payments' && ($vals['PAYMENT_D17_ENABLED'] ?? '') === 'true') {
            if (($vals['PAYMENT_D17_GSM'] ?? '') === '') $err['PAYMENT_D17_GSM'] = self::msg('gsm');
            if (($vals['PAYMENT_D17_NAME'] ?? '') === '') $err['PAYMENT_D17_NAME'] = self::msg('req');
        }
        return [$vals, $err];
    }

    /* ------------------------------------------------------------------ pages */

    public static function step(Request $q): Response
    {
        if (!InstallGate::routesWanted()) return not_found();
        $step = (string)$q->params['step'];
        $steps = Fields::steps();
        if ($step === 'done') return self::done();
        if (!isset($steps[$step])) return not_found();
        if (InstallGate::installed()) return Response::redirect(url('/install/done/'));
        $st = &$_SESSION['inst'];
        $st ??= ['v' => [], 'ok' => []];
        foreach (Fields::ORDER as $s) { // no skipping ahead
            if ($s === $step) break;
            if (empty($st['ok'][$s])) return Response::redirect(url('/install/' . $s . '/'));
        }
        $errors = []; $posted = null; $info = '';
        if ($q->isPost()) {
            if (!Csrf::check($q)) { $info = 'csrf'; }
            elseif (!RateLimit::hit('inst-post:' . $q->ip(), 200, 600)) { $info = 'rate'; }
            elseif ($step === 'requirements') {
                if (SysCheck::failCount(SysCheck::requirements(null, true, true)) === 0) { $st['ok'][$step] = true; return Response::redirect(url('/install/' . self::next($step) . '/')); }
                $info = 'reqfail';
            } elseif ($step === 'review') {
                return self::runInstall($q);
            } else {
                [$vals, $errors] = self::validate($step, $q->post, $st['v']);
                if (!$errors) {
                    $st['v'] = $vals + $st['v'];
                    $st['ok'][$step] = true;
                    return Response::redirect(url('/install/' . self::next($step) . '/'));
                }
                $posted = $q->post;
            }
        }
        return self::render($step, $errors, $posted, $info);
    }

    private static function runInstall(Request $q): Response
    {
        $st = &$_SESSION['inst'];
        if (!Csrf::check($q)) return self::render('review', [], null, 'csrf');
        if (!RateLimit::hit('inst-run:' . $q->ip(), 6, 600)) return self::render('review', [], null, 'rate');
        $problems = self::reviewProblems($st['v']);
        if ($problems) return self::render('review', [], null, 'problems');
        $v = $st['v'];
        if (($v['CRON_MODE'] ?? '') === 'web' && strlen((string)($v['CRON_TOKEN'] ?? '')) < 24) $v['CRON_TOKEN'] = Cron::newToken();
        $res = Runner::install($v);
        if (!$res['ok']) { $_SESSION['inst_error'] = $res; return self::render('review', [], null, 'failed'); }
        $_SESSION['inst_done'] = [
            'url' => $v['APP_URL'], 'mount' => $v['ADMIN_URL_PATH'], 'cron' => $v['CRON_MODE'], 'token' => $v['CRON_TOKEN'] ?? '', 'base' => $v['APP_BASE_PATH'] ?? '',
            'mail' => $v['MAIL_DRIVER'], 'pw' => isset($v['WEBMASTER_PASSWORD_HASH']), 'log' => $res['log'],
        ];
        unset($_SESSION['inst'], $_SESSION['inst_error']);
        session_regenerate_id(true);
        return Response::redirect(url('/install/done/'));
    }

    /** Whole-wizard checks for the review step: every step finished and the cross-step rules hold. @return list<string> */
    private static function reviewProblems(array $v): array
    {
        $p = [];
        foreach (['site', 'team', 'storage', 'mail', 'payments', 'brand', 'cron'] as $s) if (empty(self::st()['ok'][$s])) $p[] = $s;
        if (($v['MAIL_DRIVER'] ?? '') === 'log' && empty($v['WEBMASTER_PASSWORD_HASH'])) $p[] = 'pwlog';
        return $p;
    }

    public static function delete(Request $q): Response
    {
        if (!InstallGate::routesWanted() || empty($_SESSION['inst_done'])) return not_found();
        if (!Csrf::check($q)) return self::done(null, 'csrf');
        Fields::steps(); // load before the files go away
        $res = Runner::deleteInstaller();
        return self::done($res);
    }

    private static function done(?array $deleted = null, string $info = ''): Response
    {
        $d = $_SESSION['inst_done'] ?? null;
        if (!$d || !InstallGate::installed()) return not_found();
        $left = SysCheck::installerFiles();
        $body = self::renderDone($d, $left, $deleted, $info);
        if ($deleted !== null && !$left) { unset($_SESSION['inst_done']); }
        return self::page(tr3('Installation finished', 'Installation terminée', 'اكتمل التثبيت'), $body, 'done');
    }

    /* ------------------------------------------------------------------ rendering */

    private static function render(string $step, array $errors, ?array $posted, string $info): Response
    {
        $steps = Fields::steps();
        $S = $steps[$step];
        $st = self::st();
        $def = self::defaults();
        $b = '<h1>' . e($S['title']) . '</h1><p class="lead">' . e($S['lead']) . '</p>';
        $b .= match ($info) {
            'csrf' => '<div class="note bad">' . e(tr3('The page expired. Please try again.', 'La page a expiré. Veuillez réessayer.', 'انتهت صلاحية الصفحة. حاول مرة أخرى.')) . '</div>',
            'rate' => '<div class="note bad">' . e(tr3('Too many attempts. Wait a few minutes and try again.', 'Trop de tentatives. Attendez quelques minutes puis réessayez.', 'محاولات كثيرة. انتظر بضع دقائق ثم أعد المحاولة.')) . '</div>',
            default => '',
        };
        if ($errors) $b .= '<div class="note bad">' . e(tr3('Some fields need your attention. Nothing has been saved for this step yet.', 'Certains champs demandent votre attention. Rien n\'a encore été enregistré pour cette étape.', 'بعض الحقول تحتاج انتباهك. لم يُحفظ شيء لهذه الخطوة بعد.')) . '</div>';

        if ($step === 'requirements') $b .= self::renderRequirements($info === 'reqfail');
        elseif ($step === 'review') $b .= self::renderReview($st['v'], $info);
        else {
            $b .= '<form method="post" action="' . e(url('/install/' . $step . '/')) . '" autocomplete="off">' . csrf_field();
            foreach ($S['fields'] as $f) $b .= self::renderField($f, $errors, $posted, $st['v'], $def);
            $b .= self::nav($step, tr3('Save and continue', 'Enregistrer et continuer', 'احفظ وتابع')) . '</form>';
        }
        return self::page($S['title'], $b, $step);
    }

    private static function nav(string $step, string $label, bool $primary = true): string
    {
        $p = self::prev($step);
        return '<div class="nav">' . ($p ? '<a class="btn ghost" href="' . e(url('/install/' . $p . '/')) . '">' . e(tr3('Back', 'Retour', 'رجوع')) . '</a>' : '<span></span>')
            . '<button class="btn' . ($primary ? '' : ' ghost') . '" type="submit">' . e($label) . '</button></div>';
    }

    private static function renderField(array $f, array $errors, ?array $posted, array $have, array $def): string
    {
        $env = $f['env']; $k = $env ?: $f['name'];
        $type = $f['type'];
        $secret = !empty($f['secret']);
        $val = $secret ? '' : (string)($posted[$k] ?? $have[$env] ?? $def[$env] ?? '');
        if ($type === 'checkbox' && $posted !== null) $val = !empty($posted[$k]) ? 'true' : 'false';
        $id = 'f-' . strtolower(str_replace('_', '-', $k));
        $o = '<div class="field' . (isset($errors[$k]) ? ' has-err' : '') . '">';
        $code = $env !== '' ? '<code>' . e($env) . '</code>' : '';
        if ($type === 'radio') {
            $o .= '<div class="lbl">' . e($f['label']) . ' ' . $code . '</div><div class="radios">';
            foreach ($f['options'] as $ov => $od) {
                $o .= '<label class="radio"><input type="radio" name="' . e($k) . '" value="' . e($ov) . '"' . ($val === $ov ? ' checked' : '') . '><span><b>' . e($od[0]) . '</b><small>' . e($od[1] ?? '') . '</small></span></label>';
            }
            $o .= '</div>';
        } elseif ($type === 'checkbox') {
            $o .= '<label class="check"><input id="' . $id . '" type="checkbox" name="' . e($k) . '" value="1"' . ($val === 'true' ? ' checked' : '') . '><span>' . e($f['label']) . ' ' . $code . '</span></label>';
        } else {
            $o .= '<label class="lbl" for="' . $id . '">' . e($f['label']) . ' ' . $code . (!empty($f['req']) ? ' <span class="req" title="' . e(tr3('Required', 'Obligatoire', 'مطلوب')) . '">*</span>' : '') . '</label>';
            if ($type === 'fixed') $o .= '<div class="fixed">' . e($f['value']) . '</div>';
            elseif ($type === 'select') {
                $o .= '<select id="' . $id . '" name="' . e($k) . '">';
                foreach ($f['options'] as $ov => $od) $o .= '<option value="' . e($ov) . '"' . ($val === $ov ? ' selected' : '') . '>' . e($od[0]) . '</option>';
                $o .= '</select>';
            } else {
                $it = $type === 'password' ? 'password' : ($type === 'email' ? 'email' : ($type === 'number' ? 'number' : 'text'));
                $extra = $type === 'number' ? ' min="' . (int)$f['min'] . '" max="' . (int)$f['max'] . '" inputmode="numeric"' : '';
                $o .= '<input id="' . $id . '" type="' . $it . '" name="' . e($k) . '" value="' . e($val) . '" placeholder="' . e($f['ph'] ?? '') . '"' . $extra
                    . ($type === 'password' ? ' autocomplete="new-password"' : ' autocomplete="off" spellcheck="false"') . ' dir="ltr">';
                if ($secret && isset($have[$env])) $o .= '<div class="hint ok">' . e(tr3('A value is already saved for this wizard and stays hidden. Leave empty to keep it.', 'Une valeur est déjà enregistrée pour cet assistant et reste masquée. Laissez vide pour la conserver.', 'توجد قيمة محفوظة لهذا المعالج وتبقى مخفية. اتركه فارغا للإبقاء عليها.')) . '</div>';
            }
        }
        if (isset($errors[$k])) $o .= '<div class="err">' . e($errors[$k]) . '</div>';
        if (($f['help'] ?? '') !== '') $o .= '<div class="hint">' . e($f['help']) . '</div>';
        return $o . '</div>';
    }

    private static function explain(array $r): string
    {
        $id = $r['id'];
        $x = substr($id, 4);
        $why = [
            'pdo_sqlite' => tr3('database (SQLite)', 'base de données (SQLite)', 'قاعدة البيانات (SQLite)'),
            'mbstring' => tr3('text in English, French and Arabic', 'texte en anglais, français et arabe', 'النص بالإنجليزية والفرنسية والعربية'),
            'json' => tr3('data files and the JSON API', 'fichiers de données et API JSON', 'ملفات البيانات وواجهة JSON'),
            'openssl' => tr3('encryption and signed links', 'chiffrement et liens signés', 'التشفير والروابط الموقّعة'),
            'curl' => tr3('payment providers and social sign-in', 'prestataires de paiement et connexion sociale', 'مزوّدو الدفع والدخول الاجتماعي'),
            'sodium' => tr3('encrypted settings (secret box)', 'réglages chiffrés (secret box)', 'الإعدادات المشفّرة (secret box)'),
            'ctype' => tr3('input checks in the settings forms', 'contrôles de saisie des formulaires de réglages', 'فحوص الإدخال في نماذج الإعدادات'),
            'session' => tr3('sign-in sessions', 'sessions de connexion', 'جلسات الدخول'),
            'filter' => tr3('e-mail and address validation', 'validation des e-mails et des adresses', 'التحقق من البريد والعناوين'),
            'gd' => tr3('owner image library: converts pictures to WebP', 'bibliothèque d\'images du propriétaire : convertit en WebP', 'مكتبة صور المالك: تحوّل الصور إلى WebP'),
            'zip' => tr3('owner image library: ZIP uploads', 'bibliothèque d\'images du propriétaire : envois ZIP', 'مكتبة صور المالك: رفع ملفات ZIP'),
            'iconv' => tr3('owner image library: clean file names', 'bibliothèque d\'images du propriétaire : noms de fichiers propres', 'مكتبة صور المالك: أسماء ملفات نظيفة'),
        ];
        if ($id === 'php') return $r['ok'] ? tr3('8.4 or newer', '8.4 ou plus récent', '8.4 أو أحدث') : tr3('too old: ask the host to select PHP 8.4 for this site and for the command line', 'trop ancien : demandez à l\'hébergeur de choisir PHP 8.4 pour ce site et pour la ligne de commande', 'قديم جدا: اطلب من المضيف اختيار PHP 8.4 لهذا الموقع ولسطر الأوامر');
        if (str_starts_with($id, 'ext:')) return $why[$x] ?? '';
        if ($id === 'argon2') return $r['ok'] ? tr3('available for password hashes', 'disponible pour les hash de mots de passe', 'متاح لبصمات كلمات المرور') : tr3('not compiled in: bcrypt is used instead (still safe)', 'non compilé : bcrypt est utilisé à la place (toujours sûr)', 'غير مُضمَّن: يُستعمل bcrypt بدلا منه (وهو آمن أيضا)');
        if (str_starts_with($id, 'dir:')) return $r['ok'] ? tr3('writable', 'accessible en écriture', 'قابل للكتابة') : tr3('missing or not writable by the PHP user: create it and make it writable (chmod 775, or chown to that user)', 'absent ou non modifiable par l\'utilisateur PHP : créez-le et rendez-le modifiable (chmod 775, ou chown vers cet utilisateur)', 'غير موجود أو لا يستطيع مستخدم PHP الكتابة فيه: أنشئه واجعله قابلا للكتابة (chmod 775 أو chown لذلك المستخدم)');
        return match ($id) {
            'file:env' => $r['ok'] ? tr3('the settings file can be written', 'le fichier de réglages peut être écrit', 'يمكن كتابة ملف الإعدادات') : tr3('cannot be created: make the project folder writable for the installer, or create an empty .env file and make it writable', 'ne peut pas être créé : rendez le dossier du projet modifiable pour l\'installateur, ou créez un fichier .env vide et rendez-le modifiable', 'لا يمكن إنشاؤه: اجعل مجلد المشروع قابلا للكتابة للمثبّت أو أنشئ ملف .env فارغا واجعله قابلا للكتابة'),
            'file:example' => $r['ok'] ? tr3('found: template of the production settings', 'trouvé : modèle des réglages de production', 'موجود: قالب إعدادات الإنتاج') : tr3('missing: only the settings asked here are written', 'absent : seuls les réglages demandés ici sont écrits', 'غير موجود: تُكتب الإعدادات المطلوبة هنا فقط'),
            'web:https' => $r['ok'] ? tr3('this page is served over https', 'cette page est servie en https', 'هذه الصفحة تُخدَّم عبر https') : tr3('this page is served over plain http: the passwords you type travel unencrypted. Turn on HTTPS first if you can', 'cette page est servie en http simple : les mots de passe saisis circulent sans chiffrement. Activez d\'abord HTTPS si possible', 'هذه الصفحة تُخدَّم عبر http العادي: كلمات المرور التي تكتبها تنتقل دون تشفير. فعّل HTTPS أولا إن أمكن'),
            'web:docroot' => $r['ok'] ? tr3('the document root is not the project folder', 'la racine du site n\'est pas le dossier du projet', 'جذر الموقع ليس مجلد المشروع') : tr3('the document root is the project folder: point it at the htdocs folder (the root .htaccess hides the internals on Apache only)', 'la racine du site est le dossier du projet : pointez-la sur le dossier htdocs (le .htaccess racine ne masque les dossiers internes que sur Apache)', 'جذر الموقع هو مجلد المشروع: وجّهه إلى مجلد htdocs (يُخفي ملف .htaccess الجذري المجلدات الداخلية على Apache فقط)'),
            'web:time' => $r['ok'] ? tr3('enough time for the last step', 'assez de temps pour la dernière étape', 'وقت كافٍ للخطوة الأخيرة') : tr3('short: the last step may need up to 30 seconds; raise it if that step times out', 'court : la dernière étape peut demander jusqu\'à 30 secondes ; augmentez-le si cette étape expire', 'قصير: قد تحتاج الخطوة الأخيرة حتى 30 ثانية؛ ارفعه إن انتهت مهلتها'),
            default => '',
        };
    }

    private static function renderRequirements(bool $failed): string
    {
        $rows = SysCheck::requirements(null, true, true);
        $groups = [
            'php' => tr3('PHP version', 'Version de PHP', 'إصدار PHP'),
            'ext' => tr3('Required PHP extensions', 'Extensions PHP obligatoires', 'امتدادات PHP المطلوبة'),
            'opt' => tr3('Optional extensions', 'Extensions facultatives', 'امتدادات اختيارية'),
            'dir' => tr3('Folders the site writes to', 'Dossiers où le site écrit', 'المجلدات التي يكتب فيها الموقع'),
            'file' => tr3('Settings files', 'Fichiers de réglages', 'ملفات الإعدادات'),
            'web' => tr3('This web server', 'Ce serveur web', 'خادم الويب هذا'),
        ];
        $o = '';
        foreach ($groups as $g => $title) {
            $set = array_filter($rows, fn($r) => $r['group'] === $g);
            if (!$set) continue;
            $o .= '<h2>' . e($title) . '</h2><table class="req"><tbody>';
            foreach ($set as $r) {
                $cls = $r['ok'] ? 'ok' : ($r['need'] === 'required' ? 'bad' : 'warn');
                $lab = $r['ok'] ? tr3('OK', 'OK', 'متوفر') : ($r['need'] === 'required' ? tr3('Missing', 'Manquant', 'ناقص') : tr3('Optional', 'Facultatif', 'اختياري'));
                $o .= '<tr class="' . $cls . '"><td class="n" dir="ltr"><code>' . e($r['name']) . '</code></td><td class="s"><span class="badge ' . $cls . '">' . e($lab) . '</span></td><td>' . e(self::explain($r)) . '</td></tr>';
            }
            $o .= '</tbody></table>';
        }
        $fails = SysCheck::failCount($rows); $warns = SysCheck::warnCount($rows);
        if ($fails) $o .= '<div class="note bad">' . e(tr3('Fix the red lines, then press Check again. How: enable the extension in your hosting panel (the PHP version or extensions screen) or in php.ini, and make the folders writable. Your host\'s support can do both in minutes.', 'Corrigez les lignes rouges, puis appuyez sur Vérifier de nouveau. Comment : activez l\'extension dans le panneau de l\'hébergeur (écran de version ou d\'extensions PHP) ou dans php.ini, et rendez les dossiers modifiables. Le support de votre hébergeur peut faire les deux en quelques minutes.', 'أصلح السطور الحمراء ثم اضغط فحص مرة أخرى. الطريقة: فعّل الامتداد من لوحة الاستضافة (شاشة إصدار PHP أو الامتدادات) أو من php.ini واجعل المجلدات قابلة للكتابة. يستطيع دعم مضيفك فعل الأمرين في دقائق.')) . '</div>';
        else $o .= '<div class="note ok">' . e($warns ? tr3('All required items are available. The amber lines only switch off an optional feature or advise you; you can continue.', 'Tous les éléments obligatoires sont disponibles. Les lignes orange désactivent seulement une fonction facultative ou vous conseillent ; vous pouvez continuer.', 'كل العناصر المطلوبة متوفرة. السطور البرتقالية تعطّل ميزة اختيارية أو تنصحك فقط؛ يمكنك المتابعة.') : tr3('All required items are available.', 'Tous les éléments obligatoires sont disponibles.', 'كل العناصر المطلوبة متوفرة.')) . '</div>';
        $o .= '<form method="post" action="' . e(url('/install/requirements/')) . '">' . csrf_field() . '<div class="nav"><span></span><button class="btn" type="submit">' . e($fails ? tr3('Check again', 'Vérifier de nouveau', 'افحص مرة أخرى') : tr3('Continue', 'Continuer', 'متابعة')) . '</button></div></form>';
        return $o;
    }

    private static function renderReview(array $v, string $info): string
    {
        $steps = Fields::steps();
        $o = '';
        if ($info === 'failed') {
            $r = $_SESSION['inst_error'] ?? ['log' => [], 'error' => ''];
            $o .= '<div class="note bad"><b>' . e(tr3('The installation stopped. Nothing was locked, so you can fix the cause and press Install again.', 'L\'installation s\'est arrêtée. Rien n\'a été verrouillé : corrigez la cause et appuyez de nouveau sur Installer.', 'توقف التثبيت. لم يُقفل شيء، فيمكنك إصلاح السبب والضغط على تثبيت مرة أخرى.')) . '</b><br><code dir="ltr">' . e($r['error']) . '</code><ul>';
            foreach ($r['log'] as [$ok, $t]) $o .= '<li>' . ($ok ? '✓ ' : '✗ ') . e($t) . '</li>';
            $o .= '</ul></div>';
        }
        $problems = self::reviewProblems($v);
        if ($problems) {
            $o .= '<div class="note bad">';
            foreach ($problems as $p) {
                if ($p === 'pwlog') $o .= '<p>' . e(self::msg('pwlog')) . '</p>';
                else $o .= '<p>' . e(tr3('This step is not finished yet:', 'Cette étape n\'est pas terminée :', 'هذه الخطوة لم تكتمل بعد:')) . ' <a href="' . e(url('/install/' . $p . '/')) . '">' . e($steps[$p]['title']) . '</a></p>';
            }
            $o .= '</div>';
        }
        $o .= '<table class="req sum"><tbody>';
        foreach (['site', 'team', 'storage', 'mail', 'payments', 'brand', 'cron'] as $s) {
            $o .= '<tr class="grp"><th colspan="2"><a href="' . e(url('/install/' . $s . '/')) . '">' . e($steps[$s]['title']) . '</a></th></tr>';
            foreach ($steps[$s]['fields'] as $f) {
                if ($f['env'] === '') continue;
                $k = $f['env'];
                if (!empty($f['secret'])) $shown = isset($v[$k]) && $v[$k] !== '' ? tr3('set (hidden)', 'défini (masqué)', 'مضبوط (مخفي)') : tr3('not set', 'non défini', 'غير مضبوط');
                elseif ($f['type'] === 'checkbox') $shown = ($v[$k] ?? 'false') === 'true' ? tr3('yes', 'oui', 'نعم') : tr3('no', 'non', 'لا');
                else { $shown = (string)($v[$k] ?? ''); if ($shown === '') $shown = '—'; }
                $o .= '<tr><td class="n" dir="ltr"><code>' . e($k) . '</code></td><td dir="auto">' . e($shown) . '</td></tr>';
            }
        }
        $o .= '</tbody></table>';
        $o .= '<p class="hint">' . e(tr3('PAYMENT_MODE stays demo and DB_DRIVER stays sqlite. Every other setting keeps the default of the .env.example template.', 'PAYMENT_MODE reste demo et DB_DRIVER reste sqlite. Tous les autres réglages gardent la valeur par défaut du modèle .env.example.', 'يبقى PAYMENT_MODE على demo ويبقى DB_DRIVER على sqlite. وتحتفظ كل الإعدادات الأخرى بقيمة قالب .env.example الافتراضية.')) . '</p>';
        $o .= '<form method="post" action="' . e(url('/install/review/')) . '">' . csrf_field()
            . self::nav('review', tr3('Install now', 'Installer maintenant', 'ثبّت الآن')) . '</form>';
        return $o;
    }

    private static function renderDone(array $d, array $left, ?array $deleted, string $info): string
    {
        $base = rtrim($d['url'], '/') . ($d['base'] ?? '');
        $portal = $base . '/' . $d['mount'] . '/';
        $o = '<h1>' . e(tr3('Installation finished', 'Installation terminée', 'اكتمل التثبيت')) . '</h1>';
        $o .= '<div class="note ok">' . e(tr3('The site is installed: the .env file is written (mode 600), the database is filled, the signing key exists and this installer is locked.', 'Le site est installé : le fichier .env est écrit (mode 600), la base est remplie, la clé de signature existe et cet installateur est verrouillé.', 'تم تثبيت الموقع: كُتب ملف .env (الصلاحية 600) وملئت قاعدة البيانات ويوجد مفتاح التوقيع وقُفل هذا المثبّت.')) . '</div>';
        if ($info === 'csrf') $o .= '<div class="note bad">' . e(tr3('The page expired. Please try again.', 'La page a expiré. Veuillez réessayer.', 'انتهت صلاحية الصفحة. حاول مرة أخرى.')) . '</div>';

        // 1. the one thing to write down
        $o .= '<h2>1. ' . e(tr3('Write down the portal address', 'Notez l\'adresse du portail', 'دوّن عنوان البوابة')) . '</h2>'
            . '<p>' . e(tr3('This is the secret address of the owner portal. Share it only with the team. It is also in your .env file as ADMIN_URL_PATH.', 'C\'est l\'adresse secrète du portail du propriétaire. Ne la partagez qu\'avec l\'équipe. Elle figure aussi dans votre fichier .env sous ADMIN_URL_PATH.', 'هذا هو العنوان السري لبوابة المالك. لا تشاركه إلا مع الفريق. وهو موجود أيضا في ملف .env تحت ADMIN_URL_PATH.')) . '</p>'
            . '<p class="big" dir="ltr"><code>' . e($portal) . '</code></p>'
            . '<p class="hint">' . e($d['pw'] ? tr3('Sign in with the owner e-mail and the password you chose.', 'Connectez-vous avec l\'e-mail du propriétaire et le mot de passe choisi.', 'سجّل الدخول ببريد المالك وكلمة المرور التي اخترتها.') : tr3('No owner password was set: the owner signs in with the 6-digit code sent by e-mail, so outgoing e-mail must work first.', 'Aucun mot de passe propriétaire défini : le propriétaire se connecte avec le code à 6 chiffres reçu par e-mail, l\'envoi d\'e-mails doit donc fonctionner d\'abord.', 'لم تُضبط كلمة مرور للمالك: يدخل المالك بالرمز المكوّن من 6 أرقام المرسل بالبريد، فيجب أن يعمل البريد الصادر أولا.')) . '</p>';
        if ($d['mail'] === 'log') $o .= '<div class="note warn">' . e(tr3('Mail is set to "log": no message leaves the server. Edit MAIL_DRIVER in .env (smtp), then send a test message (php bin/mail.php), see the Setup guide', 'L\'envoi est réglé sur « log » : aucun message ne quitte le serveur. Modifiez MAIL_DRIVER dans .env (smtp), puis envoyez un message de test (php bin/mail.php), voir le Guide de mise en place', 'الإرسال مضبوط على "log": لا تغادر أي رسالة الخادم. عدّل MAIL_DRIVER في .env (smtp) ثم أرسل رسالة تجريبية (php bin/mail.php)، انظر دليل الإعداد')) . '</div>';

        // 2. scheduled jobs
        $o .= '<h2>2. ' . e(tr3('Scheduled jobs', 'Tâches planifiées', 'المهام المجدولة')) . '</h2>' . self::cronBlock($d);

        // 3. delete installer
        $o .= '<h2>3. ' . e(tr3('Delete the installation files', 'Supprimez les fichiers d\'installation', 'احذف ملفات التثبيت')) . '</h2>';
        if ($left) {
            $o .= '<div class="note bad"><b>' . e(tr3('Delete the installation files and folder from the server now.', 'Supprimez dès maintenant du serveur les fichiers et le dossier d\'installation.', 'احذف ملفات ومجلد التثبيت من الخادم الآن.')) . '</b> '
                . e(tr3('The wizard is already locked and no longer answers, but code that is not needed should not stay on a live server. Until they are gone, the preflight and the System screen of the portal keep warning you.', 'L\'assistant est déjà verrouillé et ne répond plus, mais du code inutile ne doit pas rester sur un serveur en production. Tant qu\'ils sont présents, le preflight et l\'écran Système du portail continuent de vous avertir.', 'المعالج مقفل أصلا ولم يعد يستجيب، لكن الشيفرة غير اللازمة لا ينبغي أن تبقى على خادم يعمل. وما دامت موجودة يواصل preflight وشاشة النظام في البوابة تحذيرك.')) . '</div><ul class="files">';
            foreach ($left as $f) $o .= '<li><code dir="ltr">' . e($f) . '</code></li>';
            $o .= '</ul><p>' . e(tr3('Delete them with your FTP client or the file manager of your host, or from a terminal:', 'Supprimez-les avec votre client FTP ou le gestionnaire de fichiers de l\'hébergeur, ou depuis un terminal :', 'احذفها بعميل FTP أو بمدير الملفات لدى المضيف أو من الطرفية:')) . '</p><pre dir="ltr">cd ' . e(ROOT) . "\nrm -r install bin/install.php</pre>"
                . '<p>' . e(tr3('Or let this page try to delete them (it works only if the PHP user may delete these files):', 'Ou laissez cette page essayer de les supprimer (cela ne marche que si l\'utilisateur PHP a le droit de les supprimer) :', 'أو دع هذه الصفحة تحاول حذفها (ينجح ذلك فقط إن كان لمستخدم PHP حق حذف هذه الملفات):')) . '</p>'
                . '<form method="post" action="' . e(url('/install/delete/')) . '">' . csrf_field() . '<div class="nav"><span></span><button class="btn" type="submit">' . e(tr3('Delete the installation files now', 'Supprimer les fichiers d\'installation maintenant', 'احذف ملفات التثبيت الآن')) . '</button></div></form>'
                . '<p class="hint"><a href="' . e(url('/install/done/')) . '">' . e(tr3('Check again', 'Vérifier de nouveau', 'افحص مرة أخرى')) . '</a></p>';
            if ($deleted !== null) {
                $o .= '<div class="note bad">' . e(tr3('Some files could not be deleted by PHP. Delete them by hand as shown above.', 'PHP n\'a pas pu supprimer certains fichiers. Supprimez-les à la main comme indiqué ci-dessus.', 'لم يستطع PHP حذف بعض الملفات. احذفها يدويا كما هو موضح أعلاه.')) . '</div>';
            }
        } else {
            $o .= '<div class="note ok"><b>' . e(tr3('The installation files are gone. Nothing is left to delete.', 'Les fichiers d\'installation ont disparu. Il ne reste rien à supprimer.', 'اختفت ملفات التثبيت. لم يبقَ ما يُحذف.')) . '</b></div>';
        }

        $o .= '<h2>4. ' . e(tr3('Then', 'Ensuite', 'بعد ذلك')) . '</h2><ul class="steps">'
            . '<li>' . e(tr3('Open the portal address above and follow Setup guide in the portal.', 'Ouvrez l\'adresse du portail ci-dessus et suivez le Guide de mise en place dans le portail.', 'افتح عنوان البوابة أعلاه واتبع دليل الإعداد داخل البوابة.')) . '</li>'
            . '<li>' . e(tr3('Make sure the web server serves the htdocs folder only and that https works. Then run the preflight (php bin/preflight.php) over SSH if you have it.', 'Vérifiez que le serveur web ne sert que le dossier htdocs et que https fonctionne. Puis lancez le preflight (php bin/preflight.php) en SSH si vous l\'avez.', 'تأكد أن خادم الويب يخدم مجلد htdocs فقط وأن https يعمل. ثم شغّل preflight (php bin/preflight.php) عبر SSH إن توفر.')) . '</li>'
            . '<li>' . e(tr3('Copy storage/backups/latest.sqlite and storage/app.key off the server every day once the backup job has run.', 'Copiez chaque jour storage/backups/latest.sqlite et storage/app.key hors du serveur une fois la tâche de sauvegarde exécutée.', 'انسخ كل يوم storage/backups/latest.sqlite و storage/app.key خارج الخادم بعد تشغيل مهمة النسخ الاحتياطي.')) . '</li></ul>'
            . '<p><a class="btn" href="' . e($portal) . '">' . e(tr3('Open the portal', 'Ouvrir le portail', 'افتح البوابة')) . '</a></p>';
        return $o;
    }

    private static function cronBlock(array $d): string
    {
        $mode = $d['cron'];
        $root = ROOT;
        if ($mode === 'system') {
            return '<p>' . e(tr3('Add ONE line to the cron of your host (crontab -e over SSH, or the "Cron jobs" screen of the hosting panel). It runs every 5 minutes and starts only the jobs that are due.', 'Ajoutez UNE ligne au cron de votre hébergeur (crontab -e en SSH, ou l\'écran « Tâches cron » du panneau). Elle s\'exécute toutes les 5 minutes et ne lance que les tâches échues.', 'أضف سطرا واحدا إلى cron لدى مضيفك (crontab -e عبر SSH أو شاشة "المهام المجدولة" في لوحة الاستضافة). يعمل كل 5 دقائق ولا يشغّل إلا المهام المستحقة.')) . '</p>'
                . '<pre dir="ltr">*/5 * * * *  php ' . e($root) . '/bin/cron.php</pre>'
                . '<p class="hint">' . e(tr3('Use the path of the PHP 8.4 command-line program of your host instead of php if it differs (ask the host, or run which php over SSH). Check it worked after 10 minutes with php bin/cron.php --status (or the System screen of the portal).', 'Utilisez le chemin du programme PHP 8.4 en ligne de commande de votre hébergeur à la place de php s\'il diffère (demandez-le à l\'hébergeur, ou lancez which php en SSH). Vérifiez après 10 minutes avec php bin/cron.php --status (ou l\'écran Système du portail).', 'استعمل مسار برنامج PHP 8.4 لسطر الأوامر لدى مضيفك بدل php إن اختلف (اسأل المضيف أو شغّل which php عبر SSH). تحقق بعد 10 دقائق بالأمر php bin/cron.php --status (أو شاشة النظام في البوابة).')) . '</p>';
        }
        if ($mode === 'web') {
            $u = rtrim($d['url'], '/') . ($d['base'] ?? '') . '/cron/' . $d['token'] . '/';
            return '<p>' . e(tr3('Your host has no cron, so an outside timer will wake the site up. Create a free job at cron-job.org, UptimeRobot or any monitoring service that opens a web address on a schedule:', 'Votre hébergeur n\'a pas de cron : un minuteur externe réveillera donc le site. Créez une tâche gratuite sur cron-job.org, UptimeRobot ou tout service de supervision qui ouvre une adresse web à intervalle régulier :', 'ليس لدى مضيفك cron، لذا سيوقظ مؤقّت خارجي الموقع. أنشئ مهمة مجانية في cron-job.org أو UptimeRobot أو أي خدمة مراقبة تفتح عنوان ويب بجدول زمني:')) . '</p>'
                . '<p class="big" dir="ltr"><code>' . e($u) . '</code></p>'
                . '<ul class="steps"><li>' . e(tr3('Repeat it every 5 minutes. Each visit runs only the jobs that are due and answers in plain text.', 'Répétez-la toutes les 5 minutes. Chaque visite ne lance que les tâches échues et répond en texte brut.', 'كررها كل 5 دقائق. كل زيارة تشغّل المهام المستحقة فقط وتردّ بنص عادي.')) . '</li>'
                . '<li>' . e(tr3('Keep the address secret: it is the key. To change it, put a new long value in CRON_TOKEN in .env and update the timer. It is also stored there now.', 'Gardez l\'adresse secrète : c\'est la clé. Pour la changer, mettez une nouvelle longue valeur dans CRON_TOKEN dans .env et mettez le minuteur à jour. Elle y est déjà enregistrée.', 'أبقِ العنوان سريا: فهو المفتاح. لتغييره ضع قيمة طويلة جديدة في CRON_TOKEN داخل .env وحدّث المؤقّت. وهو محفوظ هناك الآن.')) . '</li>'
                . '<li>' . e(tr3('Check after 10 minutes: the System screen of the portal lists when each job last ran.', 'Vérifiez après 10 minutes : l\'écran Système du portail indique quand chaque tâche a tourné pour la dernière fois.', 'تحقق بعد 10 دقائق: تعرض شاشة النظام في البوابة وقت آخر تشغيل لكل مهمة.')) . '</li></ul>';
        }
        return '<p>' . e(tr3('Jobs will run while people visit the site: after a page is sent, one due job runs, at most once a minute. This needs no setup, but nothing runs while nobody visits, so on a quiet site the nightly backup copy and renewal invoices can be late. When you can, switch CRON_MODE in .env to web (outside timer) or system (host cron).', 'Les tâches s\'exécuteront pendant les visites du site : après l\'envoi d\'une page, une tâche échue s\'exécute, au plus une fois par minute. Aucune configuration n\'est nécessaire, mais rien ne tourne sans visiteur : sur un site peu visité, la copie de sauvegarde de nuit et les factures de renouvellement peuvent être en retard. Dès que possible, passez CRON_MODE dans .env à web (minuteur externe) ou system (cron de l\'hébergeur).', 'ستعمل المهام أثناء زيارات الموقع: بعد إرسال صفحة تُشغَّل مهمة مستحقة واحدة، مرة في الدقيقة على الأكثر. لا يلزم أي إعداد، لكن لا يعمل شيء ما دام لا زائر، فقد تتأخر نسخة الليل وفواتير التجديد في موقع قليل الزيارة. وحين تستطيع غيّر CRON_MODE في .env إلى web (مؤقّت خارجي) أو system (cron المضيف).')) . '</p>';
    }

    /* ------------------------------------------------------------------ layout */

    private static function page(string $title, string $body, string $step): Response
    {
        $lang = Lang::current(); $dir = Lang::dir();
        $steps = Fields::steps();
        $order = Fields::ORDER; $n = count($order); $i = (int)array_search($step, $order, true);
        $st = $_SESSION['inst'] ?? ['ok' => []];
        $nav = '<ol class="steps-nav">';
        foreach ($order as $j => $s) {
            $cls = $s === $step ? 'cur' : (!empty($st['ok'][$s]) || ($step === 'done') ? 'done' : '');
            $label = e($steps[$s]['title']);
            $nav .= '<li class="' . $cls . '">' . (!empty($st['ok'][$s]) && $s !== $step && $step !== 'done' ? '<a href="' . e(url('/install/' . $s . '/')) . '">' . $label . '</a>' : '<span>' . $label . '</span>') . '</li>';
        }
        $nav .= '</ol>';
        $sw = '';
        foreach (Lang::names() as $c => $nm) $sw .= '<a' . ($c === $lang ? ' class="cur"' : '') . ' hreflang="' . $c . '" href="' . e(url('/language/' . $c . '/?r=' . rawurlencode('/install/' . $step . '/'))) . '">' . e($nm) . '</a>';
        $css = 'body{margin:0;background:#070711;color:#e2e8f0;font:16px/1.65 system-ui,Segoe UI,Tahoma,sans-serif}a{color:#a5b4fc}main{max-width:52rem;margin:0 auto;padding:1.25rem 1rem 4rem}'
            . 'header.top{display:flex;gap:1rem;justify-content:space-between;align-items:center;flex-wrap:wrap;padding:1rem 0;border-block-end:1px solid #1e293b}.brand{font-weight:700;color:#fff;letter-spacing:.04em}.lang a{margin-inline-start:.75rem;text-decoration:none;color:#94a3b8}.lang a.cur{color:#fff;font-weight:700}'
            . '.steps-nav{list-style:none;display:flex;flex-wrap:wrap;gap:.35rem;margin:1rem 0;padding:0;font-size:.82rem}.steps-nav li{padding:.2rem .6rem;border:1px solid #1e293b;border-radius:99px;color:#64748b}.steps-nav li.cur{background:#4338ca;border-color:#4338ca;color:#fff}.steps-nav li.done{color:#86efac;border-color:#14532d}.steps-nav a{color:inherit;text-decoration:none}'
            . 'h1{font-size:1.6rem;color:#fff;margin:.5rem 0}h2{font-size:1.15rem;color:#fff;margin:1.8rem 0 .5rem}.lead{color:#94a3b8}.card{background:#0f1020;border:1px solid #1e293b;border-radius:1rem;padding:1.25rem}'
            . '.field{margin:1.1rem 0}.lbl{display:block;font-weight:600;color:#f1f5f9;margin-bottom:.3rem}.lbl code,.n code,.check code{font-size:.75rem;color:#a5b4fc;background:#1e1b4b;padding:.05rem .4rem;border-radius:.3rem;font-weight:400;margin-inline-start:.3rem}.req{color:#f87171}'
            . 'input[type=text],input[type=email],input[type=number],input[type=password],select{width:100%;box-sizing:border-box;background:#0b0c1a;color:#e2e8f0;border:1px solid #334155;border-radius:.6rem;padding:.65rem .8rem;font:inherit}input:focus,select:focus{outline:2px solid #6366f1;border-color:#6366f1}'
            . '.fixed{padding:.65rem .8rem;border:1px dashed #334155;border-radius:.6rem;color:#cbd5e1;font-family:ui-monospace,monospace}.hint{font-size:.86rem;color:#94a3b8;margin-top:.3rem}.hint.ok{color:#86efac}.err{color:#fca5a5;font-size:.9rem;margin-top:.3rem}.has-err input,.has-err select{border-color:#ef4444}'
            . '.check{display:flex;gap:.6rem;align-items:flex-start}.check input{margin-top:.4rem}.radios{display:grid;gap:.6rem}.radio{display:flex;gap:.7rem;align-items:flex-start;border:1px solid #334155;border-radius:.8rem;padding:.8rem}.radio input{margin-top:.4rem}.radio small{display:block;color:#94a3b8;font-size:.86rem;margin-top:.2rem}.radio:has(input:checked){border-color:#6366f1;background:#14142b}'
            . '.nav{display:flex;justify-content:space-between;gap:1rem;margin-top:1.8rem}.btn{display:inline-block;background:linear-gradient(135deg,#7c3aed,#6366f1);color:#fff;border:0;border-radius:.75rem;padding:.75rem 1.5rem;font:inherit;font-weight:600;text-decoration:none;cursor:pointer}.btn.ghost{background:none;border:1px solid #334155;color:#cbd5e1}'
            . '.note{border-radius:.8rem;padding:.8rem 1rem;margin:1rem 0;border:1px solid}.note.ok{background:#052e16;border-color:#166534;color:#bbf7d0}.note.bad{background:#2a0a0a;border-color:#991b1b;color:#fecaca}.note.warn{background:#2a1a05;border-color:#92400e;color:#fde68a}.note p{margin:.3rem 0}'
            . 'table.req{width:100%;border-collapse:collapse;margin:.3rem 0}table.req td,table.req th{padding:.5rem .6rem;border-block-end:1px solid #1e293b;vertical-align:top;text-align:start;font-size:.92rem}td.n{white-space:nowrap}td.s{white-space:nowrap}.badge{font-size:.75rem;font-weight:700;border-radius:99px;padding:.1rem .6rem}.badge.ok{background:#14532d;color:#bbf7d0}.badge.bad{background:#7f1d1d;color:#fecaca}.badge.warn{background:#78350f;color:#fde68a}tr.bad td:last-child{color:#fecaca}tr.grp th{background:#0f1020;color:#fff;padding-top:.9rem}'
            . 'pre{background:#0b0c1a;border:1px solid #1e293b;border-radius:.6rem;padding:.8rem;overflow:auto;direction:ltr;text-align:left;font-size:.88rem}.big{font-size:1.05rem;word-break:break-all}.big code{background:#1e1b4b;padding:.4rem .7rem;border-radius:.5rem;color:#fff}ul.files{padding-inline-start:1.2rem}ul.steps{padding-inline-start:1.2rem}';
        $h = '<!DOCTYPE html><html lang="' . $lang . '" dir="' . $dir . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">'
            . '<title>' . e($title) . ' · ' . e(tr3('Installation', 'Installation', 'التثبيت')) . ' · W5D</title><style>' . $css . '</style></head><body><main>'
            . '<header class="top"><span class="brand">W5D · ' . e(tr3('Installation', 'Installation', 'التثبيت')) . '</span><nav class="lang" aria-label="Language">' . $sw . '</nav></header>'
            . $nav . '<p class="hint">' . e(tr3('Step', 'Étape', 'الخطوة')) . ' ' . ($i + 1) . ' / ' . $n . '</p><div class="card">' . $body . '</div></main></body></html>';
        return Response::html($h)->withHeaders(['X-Robots-Tag' => 'noindex, nofollow', 'Cache-Control' => 'no-store']);
    }
}
