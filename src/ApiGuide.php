<?php
declare(strict_types=1);

namespace W5D;

/**
 * API documentation per audience, shown inside each dashboard:
 *   - portal roles (owner, admin, leader, manager): Admin portal > "API & Docs"
 *   - account roles (member, investor, customer, user): /account/api/
 *   - developers: /developers/ (public) and the "Developer reference" tab of every guide
 *
 * Public endpoints come straight from Docs::endpoints() (single source of the OpenAPI surface).
 * Session actions (account + portal) are declared here once, with the permission that gates each one.
 * Portal paths are shown ONLY inside the portal guide: the account guide never reveals the admin mount.
 */
final class ApiGuide
{
    private static function L(string $en, string $fr, string $ar): array { return ['en' => $en, 'fr' => $fr, 'ar' => $ar]; }

    /** Audiences that have a guide. 'developer' is the cross-role reference. */
    public const AUDIENCES = ['owner', 'admin', 'leader', 'manager', 'member', 'investor', 'customer', 'user', 'developer'];

    /** Public endpoints worth highlighting per audience (operationIds from Docs::endpoints()). */
    private const FOCUS = [
        'owner' => ['getPrice', 'getQuote', 'getKpis', 'createSubscription', 'sendContact'],
        'admin' => ['getPrice', 'getKpis', 'listServices', 'sendContact'],
        'leader' => ['getKpis', 'listMacros', 'listServices', 'getService'],
        'manager' => ['getKpis', 'listMacros', 'getService'],
        'member' => ['listServices', 'getService', 'checkEligibility', 'listGovernorates'],
        'investor' => ['getPrice', 'getPriceCurve', 'getQuote', 'createSubscription', 'getKpis'],
        'customer' => ['listServices', 'getService', 'listProjects', 'sendContact'],
        'user' => ['checkEligibility', 'listServices', 'listGovernorates', 'sendContact'],
        'developer' => [],
    ];

    public static function intro(string $aud): array
    {
        return match ($aud) {
            'owner' => self::L('You hold every permission. The portal actions below are the same ones the buttons in your dashboard call; each is logged with your name.', 'Vous détenez toutes les permissions. Les actions du portail ci-dessous sont celles qu’appellent les boutons de votre tableau de bord ; chacune est journalisée à votre nom.', 'تملك كل الصلاحيات. إجراءات البوابة أدناه هي نفسها التي تستدعيها أزرار لوحتك، وكل إجراء يُسجَّل باسمك.'),
            'admin' => self::L('Administrators work the queues. Your permissions are listed per action; anything outside them answers 403.', 'Les administrateurs traitent les files. Vos permissions sont indiquées par action ; tout le reste répond 403.', 'يعالج المسؤولون القوائم. صلاحياتك مذكورة لكل إجراء وأي شيء خارجها يردّ 403.'),
            'leader' => self::L('Leaders read teams, weekly work, quality and reports. The portal is read-oriented for you; writes stay with administrators and the owner.', 'Les leaders lisent équipes, travail hebdomadaire, qualité et rapports. Le portail est surtout en lecture pour vous ; l’écriture reste aux administrateurs et au propriétaire.', 'يقرأ القادة الفرق والعمل الأسبوعي والجودة والتقارير. البوابة لك للقراءة غالبا، والكتابة للمسؤولين والمالك.'),
            'manager' => self::L('Managers see their teams and the weekly work. Everything else answers 403.', 'Les managers voient leurs équipes et le travail hebdomadaire. Tout le reste répond 403.', 'يرى المديرون فرقهم والعمل الأسبوعي. أي شيء آخر يردّ 403.'),
            'member' => self::L('Members use the public read API for services and eligibility, and their own account pages. There is no key to manage: your browser session is the credential.', 'Les membres utilisent l’API publique en lecture pour les services et l’admissibilité, et leurs pages de compte. Aucune clé à gérer : votre session de navigateur est l’identifiant.', 'يستخدم الأعضاء واجهة القراءة العامة للخدمات والأهلية وصفحات حسابهم. لا مفتاح لإدارته: جلسة المتصفح هي الاعتماد.'),
            'investor' => self::L('Investors can price a commitment before making it: read the price, ask for a full schedule, then pledge. Nothing is stored by a quote.', 'Les investisseurs peuvent chiffrer un engagement avant de le prendre : lire le prix, demander un calendrier complet, puis s’engager. Un devis ne stocke rien.', 'يستطيع المستثمرون تسعير التزام قبل تنفيذه: اقرأ السعر واطلب جدولا كاملا ثم التزم. عرض السعر لا يخزّن شيئا.'),
            'customer' => self::L('Customers browse services and projects through the read API and write to the partners desk. Your requests and payments live in your account.', 'Les clients parcourent services et projets via l’API en lecture et écrivent au bureau des partenaires. Vos demandes et paiements sont dans votre compte.', 'يتصفح العملاء الخدمات والمشاريع عبر واجهة القراءة ويكتبون إلى مكتب الشركاء. طلباتك ودفعاتك في حسابك.'),
            'user' => self::L('Learners can check eligibility and browse services through the same read API the site uses, then open an applicant file from their account.', 'Les apprenants peuvent vérifier leur admissibilité et parcourir les services via la même API en lecture que le site, puis ouvrir un dossier depuis leur compte.', 'يستطيع المتعلمون فحص الأهلية وتصفح الخدمات عبر واجهة القراءة نفسها التي يستخدمها الموقع ثم فتح ملف من حسابهم.'),
            default => self::L('One public JSON API with the same data as the site, in English, French and Arabic. Reads are cached for 60 seconds; writes are rate-limited and honeypot-guarded.', 'Une API JSON publique avec les mêmes données que le site, en français, anglais et arabe. Les lectures sont mises en cache 60 secondes ; les écritures sont limitées et protégées par honeypot.', 'واجهة JSON عامة واحدة ببيانات الموقع نفسها بالإنجليزية والفرنسية والعربية. القراءة مخزنة 60 ثانية والكتابة محدودة المعدل ومحمية.'),
        };
    }

    /** Public endpoints from the OpenAPI source, focused for the audience. @return list<array> */
    public static function publicOps(string $aud): array
    {
        $focus = self::FOCUS[$aud] ?? [];
        $base = Env::baseUrl() . rtrim(url(''), '/');
        $rows = [];
        foreach (\W5D\Controllers\Docs::endpoints() as $path => $ops) {
            foreach ($ops as $method => $op) {
                $id = (string)$op['operationId'];
                $ex = $method === 'get'
                    ? 'curl "' . $base . self::sample($path) . '"'
                    : 'curl -X POST ' . $base . $path . ' -H "Content-Type: application/json" -d \'' . self::body($id) . '\'';
                $rows[] = ['method' => strtoupper($method), 'path' => $path, 'id' => $id, 'summary' => (string)$op['summary'], 'auth' => 'public', 'example' => $ex, 'focus' => $aud === 'developer' || in_array($id, $focus, true)];
            }
        }
        usort($rows, fn($a, $b) => [!$a['focus'], $a['path']] <=> [!$b['focus'], $b['path']]);
        return $rows;
    }

    private static function sample(string $path): string
    {
        return match ($path) {
            '/api/quote' => '/api/quote?parts=3&days=30&cadence=daily',
            '/api/macros/{slug}.json' => '/api/macros/built.json',
            '/api/services/{slug}.json' => '/api/services/' . (array_key_first(Content::all('services')) ?: 'service-slug') . '.json',
            '/api/projects/{slug}.json' => '/api/projects/' . (array_key_first(Content::all('projects')) ?: 'project-slug') . '.json',
            '/api/sliders/{name}.json' => '/api/sliders/home.json',
            '/api/pages/{slug}.json' => '/api/pages/pricing.json',
            default => $path . '?lang=en',
        };
    }

    private static function body(string $id): string
    {
        return match ($id) {
            'sendContact' => '{"name":"Ada","email":"ada@example.org","desk":"partners","message":"Hello"}',
            'createSubscription' => '{"mode":"checkout","full_name":"Ada","email":"ada@example.org","parts_per_day":2,"days":30,"accept":1}',
            'applyManager' => '{"name":"Ada","email":"ada@example.org","macro":"built","region":"tunis","years":8,"team":12,"profile":"https://example.org/ada","motivation":"...","scenario":"...","accept":1}',
            'checkEligibility' => '{"diploma":"licence","field":"history archives"}',
            default => '{}',
        };
    }

    /**
     * Session actions for the audience. Each carries the permission that gates it; 'allowed' is resolved against the viewer's role.
     * @return list<array{group:array,rows:list<array>}>
     */
    public static function sessionOps(string $aud): array
    {
        $admin = ltrim(parse_url(admin_url(), PHP_URL_PATH) ?: '', '/');
        $A = fn(string $m, string $p, string $perm, array $sum, string $fields) => ['method' => $m, 'path' => $p, 'perm' => $perm, 'summary' => $sum, 'fields' => $fields];
        $acct = [
            $A('POST', '/account/register/', 'public', self::L('Create an account (e-mail confirmation required).', 'Créer un compte (confirmation par e-mail requise).', 'إنشاء حساب (يتطلب تأكيد البريد).'), 'name, email, password, password2, role=user|investor|customer, accept, [next]'),
            $A('POST', '/account/login/', 'public', self::L('Sign in; optional next= returns you to the page you came from.', 'Se connecter ; next= optionnel vous ramène à la page d’origine.', 'تسجيل الدخول؛ next= اختياري يعيدك إلى الصفحة الأصلية.'), 'identifier, password, [remember], [next]'),
            $A('POST', '/account/profile/', 'account', self::L('Update name, phone and language.', 'Mettre à jour nom, téléphone et langue.', 'تحديث الاسم والهاتف واللغة.'), 'name, phone, lang'),
            $A('POST', '/account/security/', 'account', self::L('Change password, or sign out remembered devices.', 'Changer le mot de passe, ou déconnecter les appareils mémorisés.', 'تغيير كلمة المرور أو إنهاء الأجهزة المحفوظة.'), 'do=password|signout-all, current, password, password2'),
            $A('GET', '/account/{panel}/', 'account', self::L('Dashboard panels: orders, payments, applications, qcm, certificates, affiliate, notifications, profile, security, api.', 'Panneaux : parts, paiements, candidatures, notifications, profil, sécurité, api.', 'اللوحات: الطلبات والمدفوعات والطلبات المقدمة والإشعارات والملف والأمان وapi.'), ''),
        ];
        $acct = array_merge($acct, [
            $A('POST', '/account/qcm/start/', 'account', self::L('Start a QCM round from a rule (random service at your level); the server keeps the timer.', 'Démarrer une session QCM à partir d’une règle (service tiré au hasard à votre niveau) ; le serveur tient le chronomètre.', 'بدء جولة QCM من قاعدة (خدمة عشوائية في مستواك)؛ الخادم يحتفظ بالمؤقت.'), 'rule'),
            $A('POST', '/account/qcm/answer/', 'account', self::L('Autosave one answer (JSON reply). Send the CSRF token in _csrf or X-CSRF-Token.', 'Enregistrer une réponse (réponse JSON). Envoyez le jeton CSRF dans _csrf ou X-CSRF-Token.', 'حفظ إجابة (رد JSON). أرسل رمز CSRF في _csrf أو X-CSRF-Token.'), 'attempt, qid, choice[]'),
            $A('POST', '/account/qcm/attempt/{id}/submit/', 'account', self::L('Close a round and score it; with the timer over, saved answers are scored.', 'Clore une session et la noter ; une fois le temps écoulé, les réponses enregistrées sont notées.', 'إغلاق الجولة وتصحيحها؛ عند انتهاء الوقت تُصحَّح الإجابات المحفوظة.'), 'a[qid][]'),
            $A('POST', '/account/qcm/buy/', 'account', self::L('Buy a fee pass for a round (D17); verified by a person.', 'Acheter un accès payant pour une session (D17) ; vérifié par une personne.', 'شراء تصريح مدفوع لجولة (D17)؛ يتحقق منه شخص.'), 'rule'),
            $A('POST', '/account/qcm/task/', 'account', self::L('Submit the task a round requires; a person reviews it.', 'Soumettre la tâche exigée par une session ; une personne l’examine.', 'تقديم المهمة التي تتطلبها الجولة؛ يراجعها شخص.'), 'rule, note'),
            $A('POST', '/account/affiliate/apply/', 'account', self::L('Apply to the affiliate programme.', 'Candidater au programme d’affiliation.', 'التقدم إلى برنامج الإحالة.'), 'accept'),
            $A('POST', '/account/affiliate/payout/', 'account', self::L('Request a payout above the published minimum.', 'Demander un versement au-delà du minimum publié.', 'طلب صرف فوق الحد الأدنى المنشور.'), 'amount'),
        ]);
        $forms = [
            ['checkout', 'POST', '/checkout/', 'investor', 'full_name, email (forced to your account), kind, parts_per_day, days, start_on, cadence, payment_method, phone, accept'],
            ['subscription', 'POST', '/subscription/', 'investor', 'same fields, mode=subscription'],
            ['register', 'POST', '/app/register/', 'member', 'name, region, field, diploma, accept'],
            ['manager', 'POST', '/careers/managers/', 'leader', 'name, macro, region, years, team, profile, motivation, scenario, accept'],
        ];
        $gated = [];
        foreach ($forms as [$form, $m, $p, , $fields]) {
            $gated[] = ['method' => $m, 'path' => $p, 'perm' => 'gate:' . $form, 'summary' => self::L('Gated form: guests are redirected to sign-in with next=; the account e-mail is bound to the record.', 'Formulaire protégé : les invités sont redirigés vers la connexion avec next= ; l’e-mail du compte est lié à l’enregistrement.', 'نموذج محمي: يُحوَّل الزوار إلى الدخول مع next= ويُربط بريد الحساب بالسجل.'), 'fields' => $fields];
        }
        $open = [
            $A('POST', '/contact/', 'public', self::L('Write to a desk. Never gated.', 'Écrire à un bureau. Jamais protégé.', 'الكتابة إلى مكتب. غير محمي أبدا.'), 'name, email, desk, subject, message'),
            $A('POST', '/newsletter/', 'public', self::L('Subscribe to the newsletter. Never gated.', 'S’abonner à la lettre d’information. Jamais protégé.', 'الاشتراك في النشرة. غير محمي أبدا.'), 'email, accept'),
            $A('POST', '/join/eligibility/', 'public', self::L('Indicative eligibility check (gate flag off by default).', 'Vérification d’admissibilité indicative (protection désactivée par défaut).', 'فحص أهلية استرشادي (البوابة معطلة افتراضيا).'), 'diploma, field, macro'),
            $A('POST', '/pay/{ref}/', 'account', self::L('Submit a manual payment (sender + transaction reference), start an online payment (302 to the provider) or re-check it (do=check).', 'Soumettre un paiement manuel (expéditeur + référence), démarrer un paiement en ligne (302 vers le prestataire) ou le revérifier (do=check).', 'تقديم دفعة يدوية (المرسل + المرجع) أو بدء دفعة إلكترونية (302 إلى المزوّد) أو إعادة فحصها (do=check).'), 'sender, txn_ref | do=pay|check'),
            $A('POST', '/pay/hook/{provider}/', 'public', self::L('Provider webhook (flouci, konnect, paymee). Authenticated by signature, never trusted: the server always re-reads the payment from the provider. Duplicates are ignored.', 'Webhook prestataire (flouci, konnect, paymee). Authentifié par signature, jamais cru : le serveur relit toujours le paiement chez le prestataire. Les doublons sont ignorés.', 'ويب هوك المزوّد (flouci وkonnect وpaymee). يُوثَّق بالتوقيع ولا يُصدَّق: يعيد الخادم دائما قراءة الدفعة من المزوّد. تُتجاهل التكرارات.'), 'provider payload'),
            $A('POST', '/subscription/action', 'public', self::L('Pause, resume or cancel a subscription with its reference and management key.', 'Suspendre, reprendre ou annuler un abonnement avec sa référence et sa clé de gestion.', 'إيقاف الاشتراك أو استئنافه أو إلغاؤه بمرجعه ومفتاح إدارته.'), 'ref, key, do=pause|resume|cancel'),
        ];
        $portal = [
            $A('POST', '/' . $admin . 'login', 'public', self::L('Owner sign-in (credentials from the server environment).', 'Connexion propriétaire (identifiants de l’environnement serveur).', 'دخول المالك (بيانات بيئة الخادم).'), 'username, password'),
            $A('POST', '/' . $admin . 'payments/review', 'payments', self::L('Verify, reject or re-check a payment (manual or online); the buyer is e-mailed and notified.', 'Valider, rejeter ou revérifier un paiement (manuel ou en ligne) ; l’acheteur est notifié par e-mail.', 'التحقق من دفعة (يدوية أو إلكترونية) أو رفضها أو إعادة فحصها؛ يُبلَّغ المشتري بالبريد.'), 'id, do=verify|reject|recheck, note'),
            $A('POST', '/' . $admin . 'payment-methods/save', 'payment-methods', self::L('Enable/disable payment methods and save their keys (secrets stored encrypted, never shown). Each change is logged with your reason.', 'Activer/désactiver les moyens de paiement et enregistrer leurs clés (secrets chiffrés, jamais affichés). Chaque changement est journalisé avec votre raison.', 'تفعيل/تعطيل وسائل الدفع وحفظ مفاتيحها (الأسرار مشفرة ولا تُعرض). يُسجَّل كل تغيير مع سببك.'), 'on[id], item[id][field], clear[id][field], note'),
            $A('POST', '/' . $admin . 'status', 'managers|messages|investors.read', self::L('Set the status of a manager application, message or subscription (permission depends on the table).', 'Fixer le statut d’une candidature manager, d’un message ou d’un abonnement (permission selon la table).', 'ضبط حالة طلب مدير أو رسالة أو اشتراك (الصلاحية حسب الجدول).'), 'table, id, status, back'),
            $A('POST', '/' . $admin . 'progressive-pricing/publish', 'pricing', self::L('Publish the price function constants; audited and public.', 'Publier les constantes de la fonction de prix ; audité et public.', 'نشر ثوابت دالة السعر؛ مدقَّق وعلني.'), 'alpha, beta, p_prog, p_time, cap, note (>=10 chars)'),
            $A('POST', '/' . $admin . 'variables/save', 'variables', self::L('Save variables and sign-in gates (flags). Each change is logged with your reason.', 'Enregistrer variables et accès par connexion (flags). Chaque changement est journalisé avec votre raison.', 'حفظ المتغيرات وبوابات الدخول (flags). كل تغيير يُسجَّل بسببك.'), 'note (>=10 chars), v[path__with__underscores]=value, reset[path__...]=1'),
            $A('POST', '/' . $admin . 'seed', 'system', self::L('Seed or purge clearly-marked demo data. Never touches daily rollups.', 'Semer ou purger des données de démonstration clairement marquées. Ne touche jamais aux cumuls quotidiens.', 'إضافة أو حذف بيانات تجريبية موسومة. لا يمس الحصائل اليومية أبدا.'), 'do=seed|purge'),
        ];
        $groups = [];
        $G = fn(string $en, string $fr, string $ar, array $rows) => ['group' => self::L($en, $fr, $ar), 'rows' => $rows];
        $isPortal = Dash::inPortal($aud);
        if ($aud !== 'developer') $groups[] = $G('Your account', 'Votre compte', 'حسابك', $acct);
        if (in_array($aud, ['investor', 'member', 'user', 'customer', 'owner', 'admin', 'leader', 'manager'], true)) $groups[] = $G('Sign-in gated forms', 'Formulaires avec connexion', 'نماذج تتطلب الدخول', $gated);
        $groups[] = $G('Open forms', 'Formulaires ouverts', 'نماذج مفتوحة', $open);
        if ($isPortal) $groups[] = $G('Portal actions', 'Actions du portail', 'إجراءات البوابة', $portal);
        return $groups;
    }

    /** Does the viewer's role hold the permission(s) for this row? ('a|b' = any of them.) */
    public static function allowed(string $role, string $perm): bool
    {
        if (in_array($perm, ['public', 'account'], true) || str_starts_with($perm, 'gate:')) return true;
        foreach (explode('|', $perm) as $p) if (ACL::can($role, $p)) return true;
        return false;
    }

    public static function notes(string $aud): array
    {
        $n = [
            self::L('Every response is JSON for /api/*. Add ?lang=fr or ?lang=ar to any GET.', 'Toute réponse /api/* est en JSON. Ajoutez ?lang=fr ou ?lang=ar à tout GET.', 'كل ردود /api/* بصيغة JSON. أضف ?lang=fr أو ?lang=ar إلى أي GET.'),
            self::L('Errors: 404 {error:"not_found"}, 422 {error:"validation", errors:{field:msg}}, 429 {error:"rate_limited"} with Retry-After.', 'Erreurs : 404, 422 avec les champs, 429 avec Retry-After.', 'الأخطاء: 404 و422 مع الحقول و429 مع Retry-After.'),
            self::L('API writes (/api/subscriptions, /api/managers/apply, /api/contact, /api/eligibility) stay public and rate-limited at 10 per 10 minutes per IP, so agents and partners can integrate; the sign-in gate applies to the website forms only.', 'Les écritures API restent publiques et limitées à 10 par 10 minutes et par IP, pour les agents et partenaires ; la connexion obligatoire ne concerne que les formulaires du site.', 'كتابات API تبقى عامة ومحدودة بـ10 كل 10 دقائق لكل IP لتمكين الوكلاء والشركاء؛ بوابة الدخول تخص نماذج الموقع فقط.'),
            self::L('Session actions are browser form posts: send the _csrf field from the page, and keep the cookie. They are not token APIs.', 'Les actions de session sont des envois de formulaire du navigateur : envoyez le champ _csrf de la page et gardez le cookie. Ce ne sont pas des API à jeton.', 'إجراءات الجلسة هي إرسال نماذج من المتصفح: أرسل حقل _csrf من الصفحة واحتفظ بالكوكي. ليست واجهات برموز.'),
            self::L('Prices come only from the published function. Never hard-code a price or a count; read /api/price.json and /api/kpis.json.', 'Les prix viennent uniquement de la fonction publiée. Ne codez jamais un prix ou un compte ; lisez /api/price.json et /api/kpis.json.', 'الأسعار من الدالة المنشورة فقط. لا تكتب سعرا أو عددا؛ اقرأ /api/price.json و/api/kpis.json.'),
        ];
        if (Dash::inPortal($aud)) $n[] = self::L('The portal mount is secret and set by ADMIN_URL_PATH in the server environment. Never share it outside the team.', 'Le point de montage du portail est secret, défini par ADMIN_URL_PATH dans l’environnement serveur. Ne le partagez pas hors de l’équipe.', 'مسار البوابة سري ويُحدَّد بـADMIN_URL_PATH في بيئة الخادم. لا تشاركه خارج الفريق.');
        return $n;
    }

    /** Gate flags and where the owner switches them. */
    public static function gates(): array { return Gate::map(); }

    /** Descriptions + usage catalog (config/apidocs.php). */
    public static function docs(): array { static $d; return $d ??= require ROOT . '/config/apidocs.php'; }

    /** Public operations enriched with description, usage, reply shape and JS/Python snippets. */
    private static function enrichPublic(array $rows): array
    {
        $cat = self::docs()['public'];
        $base = Env::baseUrl() . rtrim(url(''), '/');
        foreach ($rows as &$r) {
            $c = $cat[$r['id']] ?? null;
            $r['desc'] = $c['desc'] ?? self::L($r['summary'], $r['summary'], $r['summary']);
            $r['use'] = $c['use'] ?? null;
            $r['returns'] = $c['returns'] ?? '';
            if ($r['method'] === 'GET') {
                $u = $base . self::sample($r['path']);
                $r['js'] = "const r = await fetch('" . $u . "');\nconst data = await r.json();";
                $r['py'] = "import requests\ndata = requests.get('" . $u . "', timeout=15).json()";
            } else {
                $u = $base . $r['path'];
                $b = self::body($r['id']);
                $r['js'] = "const r = await fetch('" . $u . "', {\n  method: 'POST',\n  headers: {'Content-Type': 'application/json'},\n  body: JSON.stringify(" . $b . ")\n});\nconst data = await r.json(); // 201, or 422 {errors:{field:msg}}";
                $r['py'] = "import requests\nr = requests.post('" . $u . "', json=" . $b . ", timeout=15)\nprint(r.status_code, r.json())";
            }
        }
        unset($r);
        return $rows;
    }

    /** Session rows enriched with description + usage (portal keys omit the secret mount). */
    private static function enrichSession(array $groups, string $role): array
    {
        $cat = self::docs()['session'];
        $admin = ltrim(parse_url(admin_url(), PHP_URL_PATH) ?: '', '/');
        foreach ($groups as &$g) foreach ($g['rows'] as &$r) {
            $k = (string)$r['path'];
            if ($admin !== '' && str_starts_with(ltrim($k, '/'), $admin)) $k = substr(ltrim($k, '/'), strlen($admin));
            $c = $cat[$k] ?? $cat[$r['path']] ?? null;
            $r['desc'] = $c['desc'] ?? $r['summary'];
            $r['use'] = $c['use'] ?? null;
            $r['ok'] = self::allowed($role, (string)$r['perm']);
        }
        unset($g, $r);
        return $groups;
    }

    /** Portal screens (menus) of a role: description, kind of screen and what the role may do there. */
    public static function screens(string $aud): array
    {
        if (!Dash::inPortal($aud) && $aud !== 'owner') return [];
        $cat = self::docs();
        $out = [];
        foreach (ACL::menus($aud) as $m) {
            $slug = $m['slug'];
            $kind = Crud::has($slug) ? 'crud' : (in_array($slug, ['manager-applications', 'subscriptions', 'inbox'], true) ? 'status' : 'table');
            $out[] = ['slug' => $slug, 'icon' => $m['icon'], 'label' => $m['label'], 'desc' => $cat['screens'][$slug] ?? null, 'kind' => $kind,
                'how' => $cat['kinds'][$kind] ?? null, 'can' => Crud::has($slug) ? Crud::allowed($aud, $slug) : [], 'scope' => Crud::has($slug) ? (Crud::def($slug)['scope'][$aud] ?? null) : null];
        }
        return $out;
    }

    /** CRUD rights of a role on every resource it can read (generated from config/resources.php + config/roles.php). */
    public static function crudRights(string $aud): array
    {
        if (!Dash::inPortal($aud) && $aud !== 'owner') return [];
        $out = [];
        foreach (Crud::defs() as $slug => $d) {
            if (!ACL::can($aud, $d['perm'])) continue;
            $out[] = ['slug' => $slug, 'title' => $d['title'], 'table' => $d['table'], 'declared' => $d['actions'], 'can' => Crud::allowed($aud, $slug),
                'scope' => $d['scope'][$aud] ?? null, 'fields' => array_map(fn($k, $f) => ['name' => $k, 'type' => $f[0], 'label' => $f[1], 'req' => !empty($f['req'])], array_keys($d['fields']), array_values($d['fields']))];
        }
        return $out;
    }

    /** Account panels with description + usage (account roles only). */
    public static function panels(string $aud): array
    {
        if (Dash::inPortal($aud) || $aud === 'developer' || $aud === 'owner') return [];
        $out = [];
        foreach (self::docs()['panels'] as $k => $c) $out[] = ['panel' => $k, 'path' => '/account/' . ($k === 'overview' ? '' : $k . '/'), 'desc' => $c['desc'], 'use' => $c['use']];
        return $out;
    }

    /** Ordered steps for the audience: the shortest path to get value from the dashboard. */
    public static function quickstart(string $aud): array
    {
        $S = fn(string $en, string $fr, string $ar) => self::L($en, $fr, $ar);
        $common = [
            $S('Sign in (or sign up) with the popup in the top bar; the page you were on is kept.', 'Connectez-vous (ou inscrivez-vous) avec la fenêtre de la barre du haut ; la page en cours est conservée.', 'سجّل الدخول (أو أنشئ حسابا) من النافذة في الشريط العلوي؛ تبقى صفحتك الحالية.'),
        ];
        $steps = match ($aud) {
            'owner', 'admin' => [
                $S('Open a menu in the left sidebar (on phones, the menu button).', 'Ouvrez un menu dans la barre latérale (sur mobile, le bouton menu).', 'افتح قائمة من الشريط الجانبي (وعلى الهاتف زر القائمة).'),
                $S('Lists with New / Edit / Delete are editable; read-only lists say so at the top. Your allowed actions are shown as badges.', 'Les listes avec Nouveau / Modifier / Supprimer sont modifiables ; les autres l’indiquent en haut. Vos actions permises sont affichées en badges.', 'القوائم التي فيها جديد/تعديل/حذف قابلة للتحرير وغيرها يُذكر أعلاها. إجراءاتك المسموحة تظهر كشارات.'),
                $S('Every write is logged in Audit Log with your name.', 'Chaque écriture est journalisée dans le journal d’audit à votre nom.', 'كل كتابة تُسجَّل في سجل التدقيق باسمك.'),
            ],
            'leader', 'manager' => [
                $S('Open Teams or Weekly Work from the sidebar.', 'Ouvrez Équipes ou Travail hebdomadaire dans la barre latérale.', 'افتح الفرق أو العمل الأسبوعي من الشريط الجانبي.'),
                $S('Managers see and edit only their own rows; leaders see all rows in their screens.', 'Les managers ne voient et ne modifient que leurs propres lignes ; les leaders voient toutes les lignes de leurs écrans.', 'يرى المديرون ويعدلون صفوفهم فقط؛ ويرى القادة كل صفوف شاشاتهم.'),
                $S('An action your role lacks is not shown; calling it anyway answers 403.', 'Une action que votre rôle n’a pas n’est pas affichée ; l’appeler quand même répond 403.', 'الإجراء غير المتاح لدورك لا يظهر؛ واستدعاؤه يردّ 403.'),
            ],
            'investor' => [
                $S('Read today’s price, quote a commitment, then pledge from Pricing or Invest.', 'Lisez le prix du jour, chiffrez un engagement, puis engagez-vous depuis Tarifs ou Investir.', 'اقرأ سعر اليوم وسعّر التزاما ثم التزم من الأسعار أو استثمر.'),
                $S('Send the D17 transaction reference in Payments; a person verifies it.', 'Envoyez la référence de transaction D17 dans Paiements ; une personne la vérifie.', 'أرسل مرجع عملية D17 في المدفوعات؛ يتحقق منه شخص.'),
                $S('Pause, resume or cancel a subscription from Orders.', 'Suspendez, reprenez ou annulez un abonnement depuis Commandes.', 'أوقف الاشتراك أو استأنفه أو ألغه من الطلبات.'),
            ],
            'customer' => [
                $S('Browse services and projects, then write to the partners desk.', 'Parcourez services et projets, puis écrivez au bureau des partenaires.', 'تصفح الخدمات والمشاريع ثم راسل مكتب الشركاء.'),
                $S('Follow your requests in Applications and Notifications.', 'Suivez vos demandes dans Candidatures et Notifications.', 'تابع طلباتك في الطلبات المقدمة والإشعارات.'),
            ],
            'developer' => [
                $S('Start with GET /api/index.json, then read price and KPIs.', 'Commencez par GET /api/index.json, puis lisez prix et indicateurs.', 'ابدأ بـ GET /api/index.json ثم اقرأ السعر والمؤشرات.'),
                $S('Add ?lang=fr or ar to any GET. Writes are public but limited to 10 per 10 minutes per IP.', 'Ajoutez ?lang=fr ou ar à tout GET. Les écritures sont publiques mais limitées à 10 par 10 minutes et par IP.', 'أضف ?lang=fr أو ar لأي GET. الكتابة عامة لكن محدودة بـ10 كل 10 دقائق لكل IP.'),
            ],
            default => [
                $S('Check your eligibility, then open your applicant file from your account.', 'Vérifiez votre admissibilité, puis ouvrez votre dossier depuis votre compte.', 'افحص أهليتك ثم افتح ملفك من حسابك.'),
                $S('Follow its status in Applications; each change sends you a notification.', 'Suivez son statut dans Candidatures ; chaque changement vous envoie une notification.', 'تابع حالته في الطلبات المقدمة؛ كل تغيير يرسل لك إشعارا.'),
            ],
        };
        return $aud === 'developer' ? $steps : [...$common, ...$steps];
    }

    /** Everything a view needs. $role is the viewer's role (permissions), $aud the guide shown. */
    public static function build(string $aud, string $role): array
    {
        if (!in_array($aud, self::AUDIENCES, true)) $aud = 'developer';
        return ['aud' => $aud, 'intro' => self::intro($aud), 'quickstart' => self::quickstart($aud), 'public' => self::enrichPublic(self::publicOps($aud)),
            'groups' => self::enrichSession(self::sessionOps($aud), $role), 'notes' => self::notes($aud), 'gates' => self::gates(),
            'screens' => self::screens($aud), 'crud' => self::crudRights($aud), 'panels' => self::panels($aud),
            'links' => ['openapi' => url('/api/openapi.json'), 'developers' => url('/developers/'), 'agents' => url('/agents/'), 'llms' => url('/llms.txt')]];
    }
}
