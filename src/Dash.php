<?php
declare(strict_types=1);

namespace W5D;

/**
 * One dashboard per audience. Single source for: the public "your dashboard" preview on /for/{audience}/,
 * the role header + quick actions on /account/, and the role-scoped desk in the admin portal.
 * Staff roles (owner, admin, leader, manager) work in the admin portal; member, investor, customer and user in /account/.
 */
final class Dash
{
    public const STAFF_PORTAL = ['owner', 'admin', 'leader', 'manager'];

    private static function L(string $en, string $fr, string $ar): array { return ['en' => $en, 'fr' => $fr, 'ar' => $ar]; }

    /** @return array<string,array{icon:string,label:array,blurb:array,widgets:list<array>}> */
    public static function specs(): array
    {
        $L = fn(string $en, string $fr, string $ar) => self::L($en, $fr, $ar);
        return [
            'owner' => ['icon' => 'crown', 'label' => $L('Owner', 'Propriétaire', 'المالك'),
                'blurb' => $L('Everything, with a name and a reason attached to every change.', 'Tout, avec un nom et une raison attachés à chaque changement.', 'كل شيء، مع اسم وسبب لكل تغيير.'),
                'widgets' => [
                    ['layout-dashboard', $L('Command Center', 'Centre de commande', 'مركز القيادة'), $L('Pledges, payments, applicants, managers and inbox, with dated figures.', 'Engagements, paiements, candidats, managers et messages, avec des chiffres datés.', 'الالتزامات والدفعات والمتقدمون والمديرون والرسائل بأرقام مؤرخة.')],
                    ['sliders-horizontal', $L('Variables and gates', 'Variables et accès', 'المتغيرات والبوابات'), $L('Every number, date and sign-in gate is editable, with a reason and an audit trail.', 'Chaque nombre, date et accès par connexion est modifiable, avec une raison et une trace d’audit.', 'كل رقم وتاريخ وبوابة دخول قابل للتعديل مع سبب وسجل تدقيق.')],
                    ['trending-up', $L('Progressive pricing', 'Tarification progressive', 'التسعير التدريجي'), $L('Publish the price function; each publication is audited and public.', 'Publiez la fonction de prix ; chaque publication est auditée et publique.', 'انشر دالة السعر؛ كل نشر مدقَّق وعلني.')],
                    ['scroll-text', $L('Audit log', 'Journal d’audit', 'سجل التدقيق'), $L('Who changed what, and when.', 'Qui a changé quoi, et quand.', 'من غيّر ماذا ومتى.')],
                ]],
            'admin' => ['icon' => 'shield-check', 'label' => $L('Administrator', 'Administrateur', 'مسؤول'),
                'blurb' => $L('Queues first: payments, applications, then messages.', 'D’abord les files : paiements, candidatures, puis messages.', 'القوائم أولا: الدفعات ثم الطلبات ثم الرسائل.'),
                'widgets' => [
                    ['smartphone', $L('Payments queue', 'File des paiements', 'قائمة الدفعات'), $L('Verify or reject D17 transfers with a note; the buyer is notified.', 'Validez ou rejetez les virements D17 avec une note ; l’acheteur est notifié.', 'تحقق من تحويلات D17 أو ارفضها مع ملاحظة؛ يُبلَّغ المشتري.')],
                    ['user-check', $L('Manager applications', 'Candidatures de managers', 'طلبات المديرين'), $L('Read in full; ranking only sets the reading order.', 'Lues en entier ; le classement ne fixe que l’ordre de lecture.', 'تُقرأ كاملة؛ الترتيب يحدد ترتيب القراءة فقط.')],
                    ['inbox', $L('Contact inbox', 'Boîte de contact', 'صندوق الرسائل'), $L('Messages by desk, with a status you set.', 'Messages par bureau, avec un statut que vous fixez.', 'الرسائل حسب المكتب بحالة تحددها.')],
                    ['users', $L('Applicants', 'Candidats', 'المترشحون'), $L('Registered applicants and their verification status.', 'Candidats inscrits et leur état de vérification.', 'المترشحون المسجلون وحالة التحقق.')],
                ]],
            'leader' => ['icon' => 'compass', 'label' => $L('Team leader', 'Chef d’équipe', 'قائد فريق'),
                'blurb' => $L('Read the weekly work, review the fixed sample, report what you found.', 'Lisez le travail hebdomadaire, relisez l’échantillon fixe, rapportez ce que vous trouvez.', 'اقرأ العمل الأسبوعي وراجع العينة الثابتة وبلّغ بما وجدته.'),
                'widgets' => [
                    ['network', $L('Teams', 'Équipes', 'الفرق'), $L('Your managers and their teams in one macro work.', 'Vos managers et leurs équipes dans une macro-œuvre.', 'مديروك وفرقهم في عمل كبير واحد.')],
                    ['calendar-check', $L('Weekly work', 'Travail hebdomadaire', 'العمل الأسبوعي'), $L('The reviewable units delivered this week.', 'Les unités vérifiables livrées cette semaine.', 'الوحدات القابلة للمراجعة المسلَّمة هذا الأسبوع.')],
                    ['shield-check', $L('Quality and sampling', 'Qualité et échantillonnage', 'الجودة والمعاينة'), $L('The sample rate is fixed and random; it is not tuned per person.', 'Le taux d’échantillonnage est fixe et aléatoire ; il n’est pas réglé par personne.', 'نسبة المعاينة ثابتة وعشوائية ولا تُضبط لكل شخص.')],
                    ['chart-column', $L('Reports', 'Rapports', 'التقارير'), $L('Dated reports read from daily rollups.', 'Rapports datés lus depuis les cumuls quotidiens.', 'تقارير مؤرخة تُقرأ من الحصائل اليومية.')],
                ]],
            'manager' => ['icon' => 'network', 'label' => $L('Manager', 'Manager', 'مدير'),
                'blurb' => $L('Your team and its weekly work.', 'Votre équipe et son travail hebdomadaire.', 'فريقك وعمله الأسبوعي.'),
                'widgets' => [
                    ['network', $L('Teams', 'Équipes', 'الفرق'), $L('The people you lead.', 'Les personnes que vous dirigez.', 'الأشخاص الذين تقودهم.')],
                    ['calendar-check', $L('Weekly work', 'Travail hebdomadaire', 'العمل الأسبوعي'), $L('What was delivered, and what is late.', 'Ce qui est livré, et ce qui est en retard.', 'ما سُلّم وما تأخر.')],
                ]],
            'member' => ['icon' => 'users', 'label' => $L('Member', 'Membre', 'عضو'),
                'blurb' => $L('Certify first, then deliver one reviewable unit a week.', 'Certifiez d’abord, puis livrez une unité vérifiable par semaine.', 'اعتمد أولا ثم سلّم وحدة قابلة للمراجعة أسبوعيا.'),
                'widgets' => [
                    ['badge-check', $L('Your applicant file', 'Votre dossier', 'ملفك'), $L('Status of identity and eligibility checks.', 'État des vérifications d’identité et d’admissibilité.', 'حالة التحقق من الهوية والأهلية.')],
                    ['bell', $L('Notifications', 'Notifications', 'الإشعارات'), $L('Every decision about your file arrives here.', 'Chaque décision sur votre dossier arrive ici.', 'كل قرار بشأن ملفك يصل هنا.')],
                    ['layers', $L('Services that fit you', 'Services qui vous conviennent', 'الخدمات المناسبة لك'), $L('Indicative matches; a person confirms them.', 'Correspondances indicatives ; une personne les confirme.', 'مطابقات استرشادية يؤكدها شخص.')],
                    ['user-round', $L('Profile and security', 'Profil et sécurité', 'الملف والأمان'), $L('Phone, language, password and remembered devices.', 'Téléphone, langue, mot de passe et appareils mémorisés.', 'الهاتف واللغة وكلمة المرور والأجهزة المحفوظة.')],
                ]],
            'investor' => ['icon' => 'coins', 'label' => $L('Investor', 'Investisseur', 'مستثمر'),
                'blurb' => $L('Your parts, their schedules and their payments, with the price formula in plain view.', 'Vos parts, leurs calendriers et leurs paiements, avec la formule de prix à la vue de tous.', 'حصصك وجداولها ودفعاتها مع صيغة السعر ظاهرة.'),
                'widgets' => [
                    ['coins', $L('Your parts', 'Vos parts', 'حصصك'), $L('Every pledge with its full schedule and reference.', 'Chaque engagement avec son calendrier complet et sa référence.', 'كل التزام بجدوله الكامل ومرجعه.')],
                    ['smartphone', $L('Payments (D17)', 'Paiements (D17)', 'المدفوعات (D17)'), $L('Send the transaction reference; a person verifies it.', 'Envoyez la référence de transaction ; une personne la vérifie.', 'أرسل مرجع العملية ويتحقق منه شخص.')],
                    ['repeat', $L('Daily subscription', 'Abonnement quotidien', 'اشتراك يومي'), $L('Pause or cancel at any time.', 'Suspendez ou annulez à tout moment.', 'أوقف أو ألغِ في أي وقت.')],
                    ['trending-up', $L('Live price', 'Prix du jour', 'سعر اليوم'), $L('Read from the published function, never hard-coded.', 'Lu depuis la fonction publiée, jamais codé en dur.', 'يُقرأ من الدالة المنشورة ولا يُكتب يدويا.')],
                ]],
            'customer' => ['icon' => 'briefcase', 'label' => $L('Customer', 'Client', 'عميل'),
                'blurb' => $L('Describe the work, see the services, follow your requests.', 'Décrivez le travail, voyez les services, suivez vos demandes.', 'صف العمل وتصفح الخدمات وتابع طلباتك.'),
                'widgets' => [
                    ['inbox', $L('Requests to the partners desk', 'Demandes au bureau des partenaires', 'طلبات إلى مكتب الشركاء'), $L('Write once; a person answers.', 'Écrivez une fois ; une personne répond.', 'اكتب مرة وسيرد شخص.')],
                    ['layers', $L('Services and deliverables', 'Services et livrables', 'الخدمات والمخرجات'), $L('What each service delivers, with its evidence and boundary.', 'Ce que livre chaque service, avec preuves et limites.', 'ما تقدمه كل خدمة مع أدلتها وحدودها.')],
                    ['smartphone', $L('Payments', 'Paiements', 'المدفوعات'), $L('Your payment references and their status.', 'Vos références de paiement et leur état.', 'مراجع دفعاتك وحالتها.')],
                    ['user-round', $L('Profile and security', 'Profil et sécurité', 'الملف والأمان'), $L('Contact details and password.', 'Coordonnées et mot de passe.', 'بيانات الاتصال وكلمة المرور.')],
                ]],
            'user' => ['icon' => 'user-round', 'label' => $L('Learner / visitor', 'Apprenant / visiteur', 'متعلم / زائر'),
                'blurb' => $L('Find the service that fits your diploma, then open your applicant file.', 'Trouvez le service qui correspond à votre diplôme, puis ouvrez votre dossier.', 'اعثر على الخدمة المناسبة لشهادتك ثم افتح ملفك.'),
                'widgets' => [
                    ['graduation-cap', $L('Eligibility check', 'Vérification d’admissibilité', 'فحص الأهلية'), $L('Indicative only; a person confirms.', 'Indicatif seulement ; une personne confirme.', 'استرشادي فقط ويؤكده شخص.')],
                    ['badge-check', $L('Applicant file', 'Dossier de candidat', 'ملف المتقدم'), $L('Open it once, follow its status here.', 'Ouvrez-le une fois, suivez son état ici.', 'افتحه مرة وتابع حالته هنا.')],
                    ['bell', $L('Notifications', 'Notifications', 'الإشعارات'), $L('Decisions and next steps.', 'Décisions et prochaines étapes.', 'القرارات والخطوات التالية.')],
                    ['user-round', $L('Profile and security', 'Profil et sécurité', 'الملف والأمان'), $L('Phone, language, password.', 'Téléphone, langue, mot de passe.', 'الهاتف واللغة وكلمة المرور.')],
                ]],
        ];
    }

    public static function spec(string $aud): array { return self::specs()[$aud] ?? self::specs()['user']; }

    public static function inPortal(string $role): bool { return in_array($role, self::STAFF_PORTAL, true); }

    /** Where this audience's dashboard lives. */
    public static function home(string $aud): string { return self::inPortal($aud) ? admin_url() : url('/account/'); }

    /** Where this audience reads its API documentation (inside its own dashboard). */
    public static function api(string $aud): string { return self::inPortal($aud) ? admin_url('api-docs/') : url('/account/api/'); }

    /** The audience a visitor belongs to right now ('' for guests). */
    public static function current(): string
    {
        $r = Account::role();
        return $r === 'guest' ? '' : $r;
    }

    /** Header + tiles + quick actions for /account/ (member, investor, customer, user, and staff who open it). */
    public static function account(array $u, array $d): array
    {
        $role = (string)($u['role_slug'] ?? 'user');
        $L = fn(string $en, string $fr, string $ar) => self::L($en, $fr, $ar);
        $spec = self::spec($role);
        $price = Variables::get('price');
        $unread = Account::unread();
        $subsActive = count(array_filter($d['subs'] ?? [], fn($s) => ($s['status'] ?? '') === 'active'));
        $asked = 0; try { $asked = (int)DB::val('SELECT COUNT(*) FROM contact_messages WHERE email = ?', [$u['email']]); } catch (\Throwable) {}
        $app = $d['applicant']['status'] ?? null;
        $day = money((float)($price['today'] ?? $price['day'] ?? $price['unit'] ?? 0));
        $tiles = match ($role) {
            'investor' => [[$L('Parts ordered', 'Parts commandées', 'حصص مطلوبة'), (string)count($d['orders'] ?? []), $L('orders', 'commandes', 'طلبات')], [$L('Active subscriptions', 'Abonnements actifs', 'اشتراكات نشطة'), (string)$subsActive, $L('daily', 'quotidiens', 'يومية')], [$L('Price today', 'Prix du jour', 'سعر اليوم'), $day, $L('per part per day', 'par part et par jour', 'للحصة في اليوم')]],
            'customer' => [[$L('Requests sent', 'Demandes envoyées', 'طلبات مرسلة'), (string)$asked, $L('to the desks', 'aux bureaux', 'إلى المكاتب')], [$L('Payments', 'Paiements', 'المدفوعات'), (string)count($d['pays'] ?? []), $L('references', 'références', 'مراجع')], [$L('Services', 'Services', 'الخدمات'), (string)Variables::get('counts.services'), $L('to browse', 'à parcourir', 'للتصفح')]],
            default => [[$L('Applicant file', 'Dossier', 'الملف'), $app ? (string)$app : '—', $L('status', 'état', 'الحالة')], [$L('Unread notifications', 'Notifications non lues', 'إشعارات غير مقروءة'), (string)$unread, $L('waiting', 'en attente', 'بالانتظار')], [$L('Services', 'Services', 'الخدمات'), (string)Variables::get('counts.services'), $L('to browse', 'à parcourir', 'للتصفح')]],
        };
        $A = fn(string $ic, array $lb, string $href) => [$ic, $lb, $href];
        $actions = match ($role) {
            'investor' => [$A('coins', $L('Take a part', 'Prendre une part', 'اقتنِ حصة'), url('/checkout/')), $A('repeat', $L('Daily subscription', 'Abonnement quotidien', 'اشتراك يومي'), url('/subscription/')), $A('trending-up', $L('See the price curve', 'Voir la courbe de prix', 'اطّلع على منحنى السعر'), url('/pricing/'))],
            'customer' => [$A('inbox', $L('Write to the partners desk', 'Écrire au bureau des partenaires', 'اكتب إلى مكتب الشركاء'), url('/contact/') . '?desk=partners'), $A('layers', $L('Browse services', 'Parcourir les services', 'تصفح الخدمات'), url('/services/'))],
            'admin', 'leader', 'manager' => [$A('layout-dashboard', $L('Open the admin portal', 'Ouvrir le portail d’administration', 'افتح بوابة الإدارة'), admin_url())],
            default => [$A('graduation-cap', $L('Check my eligibility', 'Vérifier mon admissibilité', 'تحقق من أهليتي'), url('/join/eligibility/')), $A('badge-check', $L('Open my applicant file', 'Ouvrir mon dossier', 'افتح ملفي'), url('/app/register/')), $A('layers', $L('Browse services', 'Parcourir les services', 'تصفح الخدمات'), url('/services/'))],
        };
        $actions[] = $A('code', $L('API and documentation for my role', 'API et documentation pour mon rôle', 'واجهة API ووثائق دوري'), self::api($role));
        return ['role' => $role, 'label' => $spec['label'], 'blurb' => $spec['blurb'], 'icon' => $spec['icon'], 'tiles' => $tiles, 'actions' => $actions];
    }

    /** Role-scoped desk for the admin portal: what this role may open, and the queues it should look at first. */
    public static function desk(string $role): array
    {
        $q = [];
        $n = function (string $sql): int { try { return DB::available() ? (int)DB::val($sql) : 0; } catch (\Throwable) { return 0; } };
        $add = function (string $perm, array $label, int $count, string $href) use (&$q, $role) {
            if (ACL::can($role, $perm)) $q[] = ['label' => $label, 'count' => $count, 'href' => $href];
        };
        $add('payments', self::L('D17 payments to verify', 'Paiements D17 à vérifier', 'دفعات D17 للتحقق'), $n("SELECT COUNT(*) FROM payments WHERE status = 'pending' AND txn_ref IS NOT NULL"), admin_url('payments-d17/'));
        $add('managers', self::L('Manager applications to read', 'Candidatures de managers à lire', 'طلبات مديرين للقراءة'), $n("SELECT COUNT(*) FROM manager_applications WHERE status = 'received'"), admin_url('manager-applications/'));
        $add('messages', self::L('New messages', 'Nouveaux messages', 'رسائل جديدة'), $n("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'"), admin_url('inbox/'));
        $add('applicants', self::L('Registered applicants', 'Candidats inscrits', 'مترشحون مسجلون'), $n("SELECT COUNT(*) FROM applicants WHERE status = 'registered'"), admin_url('applicants/'));
        $add('qcm', self::L('QCM fee passes to verify', 'Accès QCM payants à vérifier', 'تصاريح QCM المدفوعة للتحقق'), $n("SELECT COUNT(*) FROM qcm_passes WHERE status = 'pending'"), admin_url('qcm-review/'));
        $add('qcm', self::L('QCM tasks to review', 'Tâches QCM à examiner', 'مهام QCM للمراجعة'), $n("SELECT COUNT(*) FROM qcm_tasks WHERE status = 'submitted'"), admin_url('qcm-review/'));
        $add('affiliates', self::L('Affiliate applications and payouts', 'Candidatures d’affiliés et versements', 'طلبات المُحيلين والصرف'), $n("SELECT (SELECT COUNT(*) FROM affiliates WHERE status = 'pending') + (SELECT COUNT(*) FROM affiliate_payouts WHERE status = 'requested')"), admin_url('affiliates/'));
        return ['label' => self::spec($role)['label'], 'blurb' => self::spec($role)['blurb'], 'queues' => $q, 'widgets' => self::spec($role)['widgets']];
    }
}
