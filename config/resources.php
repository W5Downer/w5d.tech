<?php
/**
 * CRUD resources of the portal (one per menu slug). Declarative: the engine (src/Crud.php) and the views read this file only.
 *
 *  table    SQL table (every row is written with country_id = 'tn')
 *  perm     permission base: the menu perm gates READING; "<perm>.create|update|delete" gate each write (config/roles.php or owner overrides)
 *  actions  which writes exist at all for this resource (a missing action is not offered to anyone, owner included)
 *  scope    role => column: that role only sees/edits rows whose column equals their own user id (row-level ACL)
 *  list     SELECT list for the table (aliases are the column headers)
 *  fields   name => [type, label[en,fr,ar], rules]   types: text|textarea|number|email|date|select|bool|ro
 */
$L = fn(string $en, string $fr, string $ar): array => ['en' => $en, 'fr' => $fr, 'ar' => $ar];
$services = function (): array {
    $o = ['' => '—'];
    foreach (\W5D\Content::all('services') as $s) $o[$s['slug']] = '#' . str_pad((string)$s['id'], 3, '0', STR_PAD_LEFT) . ' ' . t($s['title'], 'en');
    return $o;
};

$macros = function (): array {
    $o = ['' => '—'];
    foreach (\W5D\Content::all('macros') as $m) $o[$m['slug']] = t($m['name'], 'en');
    return $o;
};
$servicesReq = function () use ($services): array { $o = $services(); unset($o['']); return $o; };
$pick = fn(string $sql, string $fmt) => function () use ($sql, $fmt): array {
    $o = ['' => '—'];
    try { foreach (\W5D\DB::all($sql) as $r) $o[(string)$r['id']] = sprintf($fmt, $r['id'], $r['label']); } catch (\Throwable) {}
    return $o;
};
$clientsPick = $pick('SELECT id, name AS label FROM clients ORDER BY name LIMIT 500', '#%d %s');
$contractsPick = $pick("SELECT k.id AS id, (c.name || ' · ' || COALESCE(substr(k.scope,1,40),'')) AS label FROM contracts k LEFT JOIN clients c ON c.id = k.client_id ORDER BY k.id DESC LIMIT 500", '#%d %s');
$pubMail = '/^(?!.*@' . preg_quote(\W5D\StaffAuth::domain(), '/') . '$)/i';
$userGuards = ['SELECT COUNT(*) FROM teams WHERE manager_id = ?', 'SELECT COUNT(*) FROM affiliates WHERE user_id = ?', 'SELECT COUNT(*) FROM certificates WHERE user_id = ?', 'SELECT COUNT(*) FROM qcm_attempts WHERE user_id = ?'];
$userCascade = ['user_tokens' => 'user_id', 'social_accounts' => 'user_id', 'notifications' => 'user_id'];

return [
    'campaigns' => [
        'table' => 'campaigns', 'perm' => 'campaigns', 'actions' => ['create', 'update', 'delete'],
        'title' => $L('Campaign', 'Campagne', 'حملة'), 'order' => 'id DESC',
        'list' => 'id, name, channel, utm, daily_quota AS quota, active',
        'fields' => [
            'name' => ['text', $L('Name', 'Nom', 'الاسم'), 'req' => true, 'max' => 120],
            'channel' => ['select', $L('Channel', 'Canal', 'القناة'), 'options' => ['web' => 'web', 'email' => 'email', 'social' => 'social', 'events' => 'events', 'partners' => 'partners', 'other' => 'other']],
            'utm' => ['text', $L('UTM tag', 'Étiquette UTM', 'وسم UTM'), 'max' => 80, 'pattern' => '/^[A-Za-z0-9_\-.]*$/'],
            'daily_quota' => ['number', $L('Daily quota', 'Quota quotidien', 'الحصة اليومية'), 'min' => 0, 'maxv' => 100000, 'default' => 0],
            'active' => ['bool', $L('Active', 'Active', 'نشطة'), 'default' => 1],
        ],
    ],
    'academies' => [
        'table' => 'cohorts', 'perm' => 'academies', 'actions' => ['create', 'update', 'delete'],
        'title' => $L('Cohort', 'Cohorte', 'دفعة'), 'order' => 'starts_on DESC, id DESC',
        'list' => 'id, service_slug AS service, level, starts_on, seats',
        'fields' => [
            'service_slug' => ['select', $L('Service', 'Service', 'الخدمة'), 'req' => true, 'options' => $services],
            'level' => ['select', $L('Level', 'Niveau', 'المستوى'), 'options' => ['L1' => 'L1', 'L2' => 'L2', 'L3' => 'L3', 'L4' => 'L4']],
            'starts_on' => ['date', $L('Starts on', 'Début', 'تاريخ البدء')],
            'seats' => ['number', $L('Seats', 'Places', 'المقاعد'), 'min' => 0, 'maxv' => 5000, 'default' => 0],
        ],
    ],
    'teams' => [
        'table' => 'teams', 'perm' => 'teams', 'actions' => ['create', 'update', 'delete'], 'scope' => ['manager' => 'manager_id'],
        'title' => $L('Team', 'Équipe', 'فريق'), 'order' => 'id DESC',
        'list' => 'id, name, service_slug AS service, manager_id AS manager, cap, (SELECT COUNT(*) FROM team_members m WHERE m.team_id = teams.id) AS members',
        'fields' => [
            'name' => ['text', $L('Name', 'Nom', 'الاسم'), 'req' => true, 'max' => 120],
            'service_slug' => ['select', $L('Service', 'Service', 'الخدمة'), 'req' => true, 'options' => $services],
            'manager_id' => ['number', $L('Manager (user id)', 'Manager (id utilisateur)', 'المدير (معرّف المستخدم)'), 'min' => 1, 'maxv' => 99999999, 'scope_lock' => true],
            'cap' => ['number', $L('Capacity', 'Capacité', 'السعة'), 'min' => 1, 'maxv' => 200, 'default' => 12],
        ],
    ],
    'clients' => [
        'table' => 'clients', 'perm' => 'clients', 'actions' => ['create', 'update', 'delete'], 'in_use' => ['SELECT COUNT(*) FROM contracts WHERE client_id = ?'],
        'title' => $L('Client', 'Client', 'عميل'), 'order' => 'id DESC',
        'list' => 'id, name, email, (SELECT COUNT(*) FROM contracts k WHERE k.client_id = clients.id) AS contracts, created_at',
        'fields' => [
            'name' => ['text', $L('Name', 'Nom', 'الاسم'), 'req' => true, 'max' => 120],
            'email' => ['email', $L('E-mail', 'E-mail', 'البريد'), 'max' => 160],
        ],
    ],
    'support' => [
        'table' => 'tickets', 'perm' => 'support', 'actions' => ['create', 'update', 'delete'],
        'title' => $L('Ticket', 'Ticket', 'تذكرة'), 'order' => 'id DESC',
        'list' => 'id, subject, requester, status, due_at, created_at',
        'fields' => [
            'subject' => ['text', $L('Subject', 'Sujet', 'الموضوع'), 'req' => true, 'max' => 160],
            'body' => ['textarea', $L('Details', 'Détails', 'التفاصيل'), 'max' => 4000],
            'requester' => ['text', $L('Requester (e-mail)', 'Demandeur (e-mail)', 'مقدّم الطلب (البريد)'), 'max' => 160],
            'status' => ['select', $L('Status', 'Statut', 'الحالة'), 'options' => ['open' => 'open', 'in_progress' => 'in_progress', 'resolved' => 'resolved', 'closed' => 'closed'], 'default' => 'open'],
            'due_at' => ['date', $L('Due date', 'Échéance', 'تاريخ الاستحقاق')],
        ],
    ],
    'payroll' => [
        'table' => 'payroll_runs', 'perm' => 'payroll', 'actions' => ['create', 'update', 'delete'],
        'title' => $L('Payroll run (simulated)', 'Cycle de paie (simulé)', 'دورة أجور (محاكاة)'), 'order' => 'period DESC, id DESC',
        'list' => 'id, period, posts, amount, status',
        'fields' => [
            'period' => ['text', $L('Period (YYYY-MM)', 'Période (AAAA-MM)', 'الفترة (YYYY-MM)'), 'req' => true, 'max' => 7, 'pattern' => '/^\d{4}-(0[1-9]|1[0-2])$/'],
            'posts' => ['number', $L('Posts', 'Postes', 'الوظائف'), 'min' => 0, 'maxv' => 1000000, 'default' => 0],
            'amount' => ['number', $L('Amount', 'Montant', 'المبلغ'), 'min' => 0, 'maxv' => 1000000000, 'step' => '0.01', 'default' => 0],
            'status' => ['select', $L('Status', 'Statut', 'الحالة'), 'options' => ['simulated' => 'simulated', 'approved' => 'approved', 'paid' => 'paid'], 'default' => 'simulated'],
        ],
    ],
    'applicants' => [
        'table' => 'applicants', 'perm' => 'applicants', 'actions' => ['create', 'update', 'delete'],
        'in_use' => ['SELECT COUNT(*) FROM verifications WHERE applicant_id = ?', 'SELECT COUNT(*) FROM weekly_reports WHERE applicant_id = ?', 'SELECT COUNT(*) FROM cert_submissions WHERE applicant_id = ?', 'SELECT COUNT(*) FROM team_members WHERE applicant_id = ?'],
        'title' => $L('Applicant', 'Candidat', 'مترشح'), 'order' => 'id DESC',
        'list' => 'id, full_name, email, macro_slug AS macro, service_slug AS service, diploma, status, created_at',
        'fields' => [
            'cin' => ['text', $L('National ID (set once)', 'Pièce d’identité (saisie une fois)', 'رقم الهوية (يُدخل مرة واحدة)'), 'req' => true, 'max' => 40, 'pattern' => '/^[A-Za-z0-9\-]{3,40}$/', 'create_only' => true],
            'full_name' => ['text', $L('Full name', 'Nom complet', 'الاسم الكامل'), 'req' => true, 'max' => 160],
            'email' => ['email', $L('E-mail', 'E-mail', 'البريد'), 'max' => 160],
            'macro_slug' => ['select', $L('Macro work', 'Macro-œuvre', 'العمل الكبير'), 'options' => $macros],
            'service_slug' => ['select', $L('Service', 'Service', 'الخدمة'), 'options' => $services],
            'lang' => ['select', $L('Language', 'Langue', 'اللغة'), 'options' => ['en' => 'en', 'fr' => 'fr', 'ar' => 'ar']],
            'phone' => ['text', $L('Phone', 'Téléphone', 'الهاتف'), 'max' => 40, 'pattern' => '/^\+?[0-9 ().\-]{0,20}$/'],
            'diploma' => ['select', $L('Diploma', 'Diplôme', 'الشهادة'), 'options' => ['licence' => 'licence', 'maitrise' => 'maitrise', 'engineer' => 'engineer', 'phd' => 'phd', 'other' => 'other']],
            'status' => ['select', $L('Status', 'Statut', 'الحالة'), 'options' => ['registered' => 'registered', 'verified' => 'verified', 'certified' => 'certified', 'rejected' => 'rejected']],
        ],
    ],
    'verification' => [
        'table' => 'verifications', 'perm' => 'verification', 'actions' => ['create', 'update', 'delete'],
        'title' => $L('Verification', 'Vérification', 'تحقق'), 'order' => 'id DESC',
        'list' => 'id, applicant_id AS applicant, kind, result, reviewer_id AS reviewer, created_at',
        'fields' => [
            'applicant_id' => ['number', $L('Applicant (id)', 'Candidat (id)', 'المترشح (المعرّف)'), 'req' => true, 'min' => 1, 'maxv' => 99999999, 'create_only' => true],
            'kind' => ['text', $L('Kind of check', 'Type de contrôle', 'نوع التحقق'), 'req' => true, 'max' => 40, 'pattern' => '/^[A-Za-z0-9_\- ]{2,40}$/', 'create_only' => true],
            'result' => ['select', $L('Result', 'Résultat', 'النتيجة'), 'options' => ['pending' => 'pending', 'passed' => 'passed', 'failed' => 'failed']],
        ],
        'stamp' => ['reviewer_id' => 'uid'],
    ],
    'weekly' => [
        'table' => 'weekly_reports', 'perm' => 'weekly', 'actions' => ['create', 'update', 'delete'], 'scope' => ['manager' => 'manager_id'],
        'title' => $L('Weekly report', 'Rapport hebdomadaire', 'تقرير أسبوعي'), 'order' => 'id DESC',
        'list' => 'id, week_id AS week, applicant_id AS member, service_slug AS service, units, status, substr(COALESCE(evidence, \'\'),1,100) AS evidence, created_at',
        'fields' => [
            'week_id' => ['text', $L('Week (YYYY-Www)', 'Semaine (AAAA-Sss)', 'الأسبوع (YYYY-Www)'), 'req' => true, 'max' => 8, 'pattern' => '/^\d{4}-W(0[1-9]|[1-4]\d|5[0-3])$/', 'create_only' => true],
            'applicant_id' => ['number', $L('Member (applicant id)', 'Membre (id candidat)', 'العضو (معرّف المترشح)'), 'req' => true, 'min' => 1, 'maxv' => 99999999, 'create_only' => true],
            'service_slug' => ['select', $L('Service', 'Service', 'الخدمة'), 'req' => true, 'options' => $servicesReq, 'create_only' => true],
            'manager_id' => ['number', $L('Manager (user id)', 'Manager (id utilisateur)', 'المدير (معرّف المستخدم)'), 'min' => 1, 'maxv' => 99999999, 'scope_lock' => true],
            'units' => ['number', $L('Units', 'Unités', 'الوحدات'), 'min' => 0, 'maxv' => 100000],
            'status' => ['select', $L('Status', 'Statut', 'الحالة'), 'options' => ['submitted' => 'submitted', 'approved' => 'approved', 'rejected' => 'rejected']],
            'evidence' => ['textarea', $L('Evidence', 'Preuves', 'الأدلة'), 'max' => 4000],
        ],
    ],
    'quality' => [
        'table' => 'cert_submissions', 'perm' => 'quality', 'actions' => ['create', 'update', 'delete'],
        'title' => $L('Certification submission', 'Soumission de certification', 'طلب اعتماد'), 'order' => 'id DESC',
        'list' => 'id, applicant_id AS applicant, service_slug AS service, substr(COALESCE(task, \'\'),1,100) AS task, status, reviewer_id AS reviewer, created_at',
        'fields' => [
            'applicant_id' => ['number', $L('Applicant (id)', 'Candidat (id)', 'المترشح (المعرّف)'), 'req' => true, 'min' => 1, 'maxv' => 99999999, 'create_only' => true],
            'service_slug' => ['select', $L('Service', 'Service', 'الخدمة'), 'req' => true, 'options' => $servicesReq, 'create_only' => true],
            'task' => ['textarea', $L('Task', 'Tâche', 'المهمة'), 'req' => true, 'max' => 4000, 'create_only' => true],
            'status' => ['select', $L('Status', 'Statut', 'الحالة'), 'options' => ['submitted' => 'submitted', 'approved' => 'approved', 'rejected' => 'rejected']],
        ],
        'stamp' => ['reviewer_id' => 'uid'],
    ],
    'qcm-rules' => [
        'table' => 'qcm_rules', 'perm' => 'qcm-rules', 'actions' => ['create', 'update', 'delete'],
        'title' => $L('QCM round', 'Session QCM', 'جولة اختبار'), 'order' => 'sort, id',
        'list' => 'id, name, purpose, level, question_count AS questions, minutes, pass_pct AS pass, fee, min_level, min_affiliates AS min_aff, min_tasks, min_practice_pct AS min_practice, cooldown_hours AS cooldown, max_per_day AS per_day, active',
        'fields' => [
            'name' => ['text', $L('Name', 'Nom', 'الاسم'), 'req' => true, 'max' => 80],
            'purpose' => ['select', $L('Purpose', 'Objectif', 'الغرض'), 'options' => ['practice' => 'practice', 'certification' => 'certification', 'upgrade' => 'upgrade']],
            'level' => ['select', $L('Question level', 'Niveau des questions', 'مستوى الأسئلة'), 'options' => ['1' => 'L1', '2' => 'L2', '3' => 'L3', '4' => 'L4']],
            'service_slug' => ['select', $L('Fixed service (empty = random)', 'Service fixe (vide = aléatoire)', 'خدمة ثابتة (فارغ = عشوائي)'), 'options' => $services],
            'question_count' => ['number', $L('Questions per round', 'Questions par session', 'أسئلة الجولة'), 'min' => 1, 'maxv' => 100, 'default' => 10],
            'minutes' => ['number', $L('Minutes', 'Minutes', 'الدقائق'), 'min' => 1, 'maxv' => 240, 'default' => 10],
            'pass_pct' => ['number', $L('Pass mark %', 'Seuil de réussite %', 'نسبة النجاح %'), 'min' => 1, 'maxv' => 100, 'default' => 70],
            'fee' => ['number', $L('Fee for the next round (0 = free)', 'Frais pour la session (0 = gratuit)', 'رسوم الجولة (0 = مجانا)'), 'min' => 0, 'maxv' => 100000, 'step' => 0.5, 'default' => 0],
            'min_level' => ['select', $L('Minimum certified level', 'Niveau certifié minimal', 'أدنى مستوى معتمد'), 'options' => ['0' => '—', '1' => 'L1', '2' => 'L2', '3' => 'L3']],
            'min_affiliates' => ['number', $L('Minimum affiliate sign-ups', 'Inscriptions d’affiliés minimales', 'أدنى عدد تسجيلات الإحالة'), 'min' => 0, 'maxv' => 10000, 'default' => 0],
            'min_tasks' => ['number', $L('Minimum approved tasks', 'Tâches approuvées minimales', 'أدنى عدد مهام مقبولة'), 'min' => 0, 'maxv' => 1000, 'default' => 0],
            'min_practice_pct' => ['number', $L('Minimum best practice score %', 'Meilleur score d’entraînement minimal %', 'أدنى أفضل نتيجة تدريب %'), 'min' => 0, 'maxv' => 100, 'default' => 0],
            'cooldown_hours' => ['number', $L('Wait between rounds (hours)', 'Attente entre sessions (heures)', 'الانتظار بين الجولات (ساعات)'), 'min' => 0, 'maxv' => 8760, 'default' => 0],
            'max_per_day' => ['number', $L('Maximum rounds per day (0 = no limit)', 'Sessions max par jour (0 = illimité)', 'أقصى جولات يوميا (0 = بلا حد)'), 'min' => 0, 'maxv' => 1000, 'default' => 0],
            'sort' => ['number', $L('Sort order', 'Ordre', 'الترتيب'), 'min' => 0, 'maxv' => 9999, 'default' => 100],
            'active' => ['bool', $L('Active', 'Active', 'نشطة'), 'default' => 1],
        ],
    ],
    'team' => [
        // Team access: the ONLY place a team address is created. Sign-in is by password or by e-mailed code (StaffAuth). The password is write-only here:
        // empty keeps the current one, a value is hashed (Argon2id) and never shown, logged or mailed.
        'table' => 'users', 'perm' => 'team', 'actions' => ['create', 'update', 'delete'], 'where' => "role_slug IN ('admin','leader','manager','member')", 'in_use' => $userGuards, 'cascade' => $userCascade,
        'on_create' => fn(array $v, int $uid) => ['email_verified_at' => date('Y-m-d H:i:s')],
        'title' => $L('Team member', 'Membre de l’équipe', 'عضو فريق'), 'order' => 'id DESC',
        'list' => 'id, name, email, role_slug AS role, active, COALESCE(last_login_at, \'\') AS last_login, created_at',
        'fields' => [
            'email' => ['email', $L('Team e-mail (the sign-in address)', 'E-mail d’équipe (adresse de connexion)', 'بريد الفريق (عنوان الدخول)'), 'req' => true, 'max' => 120, 'pattern' => '/^[A-Za-z0-9._%+\-]+@' . preg_quote(\W5D\StaffAuth::domain(), '/') . '$/'],
            'name' => ['text', $L('Name', 'Nom', 'الاسم'), 'req' => true, 'max' => 120],
            'role_slug' => ['select', $L('Role', 'Rôle', 'الدور'), 'req' => true, 'options' => ['admin' => 'admin', 'leader' => 'leader (super manager)', 'manager' => 'manager', 'member' => 'member']],
            'password_hash' => ['password', $L('Password (optional: the e-mailed code always works)', 'Mot de passe (facultatif : le code par e-mail fonctionne toujours)', 'كلمة المرور (اختيارية: الرمز البريدي يعمل دائما)'), 'max' => 200],
            'active' => ['bool', $L('Active (can sign in)', 'Actif (peut se connecter)', 'نشط (يمكنه الدخول)'), 'default' => 1],
        ],
    ],
    'users' => [
        // password_hash and tokens are never selected, shown or written here.
        // Public accounts only. Team roles (admin/leader/manager/member) are created and changed in the Team menu (e-mail code sign-in),
        // so this screen can neither promote a public account to the team nor edit a team member.
        'table' => 'users', 'perm' => 'users', 'actions' => ['create', 'update', 'delete'], 'where' => "role_slug NOT IN ('admin','leader','manager','member')", 'in_use' => $userGuards, 'cascade' => $userCascade,
        'on_create' => fn(array $v, int $uid) => ['email_verified_at' => date('Y-m-d H:i:s')],
        'title' => $L('User', 'Utilisateur', 'مستخدم'), 'order' => 'id DESC',
        'list' => 'id, name, email, role_slug AS role, lang, active, CASE WHEN email_verified_at IS NULL THEN \'no\' ELSE \'yes\' END AS verified, COALESCE(last_login_at, \'\') AS last_login, created_at',
        'fields' => [
            'email' => ['email', $L('E-mail (set once)', 'E-mail (saisi une fois)', 'البريد (يُدخل مرة واحدة)'), 'req' => true, 'max' => 160, 'pattern' => $pubMail, 'create_only' => true],
            'name' => ['text', $L('Name', 'Nom', 'الاسم'), 'max' => 120],
            'role_slug' => ['select', $L('Role', 'Rôle', 'الدور'), 'req' => true, 'options' => ['investor' => 'investor', 'customer' => 'customer', 'user' => 'user']],
            'lang' => ['select', $L('Language', 'Langue', 'اللغة'), 'options' => ['en' => 'en', 'fr' => 'fr', 'ar' => 'ar']],
            'password_hash' => ['password', $L('Password (required for a new account; empty keeps the current one)', 'Mot de passe (obligatoire pour un nouveau compte ; vide = inchangé)', 'كلمة المرور (مطلوبة لحساب جديد؛ الفراغ يُبقي الحالية)'), 'req' => true, 'max' => 200],
            'active' => ['bool', $L('Active', 'Actif', 'نشط'), 'default' => 1],
        ],
    ],

    // ------------------------------------------------------------------ reference data
    'countries' => [
        // The country code is the key; deletion is refused while regions, applicants or accounts still use it.
        'table' => 'countries', 'pk' => 'rowid', 'no_country' => true, 'perm' => 'countries', 'actions' => ['create', 'update', 'delete'],
        'in_use' => ['SELECT COUNT(*) FROM regions WHERE country_id = (SELECT country_id FROM countries WHERE rowid = ?)', 'SELECT COUNT(*) FROM applicants WHERE country_id = (SELECT country_id FROM countries WHERE rowid = ?)', 'SELECT COUNT(*) FROM users WHERE country_id = (SELECT country_id FROM countries WHERE rowid = ?)'],
        'title' => $L('Country', 'Pays', 'دولة'), 'order' => 'country_id',
        'list' => 'rowid AS id, country_id AS code, iso, name_en AS name, currency, timezone, active, created_at',
        'fields' => [
            'country_id' => ['text', $L('Code (2 letters, set once)', 'Code (2 lettres, saisi une fois)', 'الرمز (حرفان، يُدخل مرة واحدة)'), 'req' => true, 'max' => 2, 'pattern' => '/^[a-z]{2}$/', 'create_only' => true],
            'iso' => ['text', $L('ISO code (2 capital letters)', 'Code ISO (2 majuscules)', 'رمز ISO (حرفان كبيران)'), 'req' => true, 'max' => 2, 'pattern' => '/^[A-Z]{2}$/'],
            'name_en' => ['text', $L('Name', 'Nom', 'الاسم'), 'req' => true, 'max' => 80],
            'currency' => ['text', $L('Currency (3 capital letters)', 'Devise (3 majuscules)', 'العملة (3 أحرف كبيرة)'), 'req' => true, 'max' => 3, 'pattern' => '/^[A-Z]{3}$/'],
            'timezone' => ['text', $L('Time zone', 'Fuseau horaire', 'المنطقة الزمنية'), 'req' => true, 'max' => 60, 'pattern' => '/^[A-Za-z0-9_\/+\-]{3,60}$/'],
            'active' => ['bool', $L('Active', 'Actif', 'نشط'), 'default' => 1],
        ],
    ],
    'regions-hubs' => [
        'table' => 'regions', 'perm' => 'regions', 'actions' => ['create', 'update', 'delete'],
        'in_use' => ['SELECT COUNT(*) FROM applicants WHERE region_id = ?', 'SELECT COUNT(*) FROM users WHERE region_id = ?'],
        'title' => $L('Region or hub', 'Région ou pôle', 'جهة أو مركز'), 'order' => 'name_en',
        'list' => 'id, slug, name_en AS en, name_fr AS fr, name_ar AS ar, quota',
        'fields' => [
            'slug' => ['text', $L('Slug (set once)', 'Identifiant (saisi une fois)', 'المعرّف (يُدخل مرة واحدة)'), 'req' => true, 'max' => 60, 'pattern' => '/^[a-z0-9][a-z0-9\-]{1,59}$/', 'create_only' => true],
            'name_en' => ['text', $L('Name (English)', 'Nom (anglais)', 'الاسم (الإنجليزية)'), 'req' => true, 'max' => 80],
            'name_fr' => ['text', $L('Name (French)', 'Nom (français)', 'الاسم (الفرنسية)'), 'max' => 80],
            'name_ar' => ['text', $L('Name (Arabic)', 'Nom (arabe)', 'الاسم (العربية)'), 'max' => 80],
            'quota' => ['number', $L('Quota', 'Quota', 'الحصة'), 'min' => 0, 'maxv' => 1000000, 'default' => 0],
        ],
    ],
    'services' => [
        // Country switch-board for the 130 services (the service texts themselves are content files in git).
        'table' => 'services_country', 'perm' => 'services', 'actions' => ['create', 'update', 'delete'],
        'title' => $L('Service in the country', 'Service dans le pays', 'خدمة في الدولة'), 'order' => 'id',
        'list' => 'id, service_slug AS service, active, seats, leader_id AS leader',
        'fields' => [
            'service_slug' => ['select', $L('Service (set once)', 'Service (saisi une fois)', 'الخدمة (تُدخل مرة واحدة)'), 'req' => true, 'options' => $servicesReq, 'create_only' => true],
            'active' => ['bool', $L('Open', 'Ouvert', 'مفتوحة'), 'default' => 0],
            'seats' => ['number', $L('Seats', 'Places', 'المقاعد'), 'min' => 0, 'maxv' => 100000, 'default' => 0],
            'leader_id' => ['number', $L('Service leader (user id)', 'Responsable du service (id utilisateur)', 'قائد الخدمة (معرّف المستخدم)'), 'min' => 1, 'maxv' => 99999999],
        ],
    ],

    // ------------------------------------------------------------------ people and money
    'investors' => [
        'table' => 'investors', 'perm' => 'investors.read', 'wperm' => 'investors', 'actions' => ['create', 'update', 'delete'],
        'in_use' => ['SELECT COUNT(*) FROM investor_orders WHERE investor_id = ?', 'SELECT COUNT(*) FROM investor_subscriptions WHERE investor_id = ?', 'SELECT COUNT(*) FROM payments WHERE investor_id = ?'],
        'title' => $L('Investor', 'Investisseur', 'مستثمر'), 'order' => 'id DESC',
        'list' => 'id, full_name, email, kind, organisation, (SELECT COUNT(*) FROM investor_orders o WHERE o.investor_id = investors.id) AS orders, (SELECT COALESCE(SUM(o.total),0) FROM investor_orders o WHERE o.investor_id = investors.id) AS pledged, created_at',
        'fields' => [
            'email' => ['email', $L('E-mail (set once)', 'E-mail (saisi une fois)', 'البريد (يُدخل مرة واحدة)'), 'req' => true, 'max' => 160, 'create_only' => true],
            'full_name' => ['text', $L('Full name', 'Nom complet', 'الاسم الكامل'), 'req' => true, 'max' => 160],
            'phone' => ['text', $L('Phone', 'Téléphone', 'الهاتف'), 'max' => 40, 'pattern' => '/^\+?[0-9 ().\-]{0,20}$/'],
            'organisation' => ['text', $L('Organisation', 'Organisation', 'الجهة'), 'max' => 160],
            'kind' => ['select', $L('Kind', 'Type', 'النوع'), 'options' => ['individual' => 'individual', 'company' => 'company']],
            'lang' => ['select', $L('Language', 'Langue', 'اللغة'), 'options' => ['en' => 'en', 'fr' => 'fr', 'ar' => 'ar']],
            'accepted_limits' => ['bool', $L('Accepted the limits notice', 'A accepté l’avis de limites', 'قبل إشعار الحدود'), 'default' => 0],
        ],
    ],
    'subscriptions' => [
        // Created by the public checkout; the owner can correct, pause, cancel or remove one.
        'table' => 'investor_subscriptions', 'perm' => 'investors.read', 'wperm' => 'subscriptions', 'actions' => ['update', 'delete'],
        'title' => $L('Daily subscription', 'Abonnement quotidien', 'اشتراك يومي'), 'order' => 'id DESC',
        'list' => 'id, order_ref AS ref, (SELECT i.full_name FROM investors i WHERE i.id = investor_subscriptions.investor_id) AS investor, parts_per_day AS parts, macro_slug AS macro, next_charge_on AS next_charge, status, created_at',
        'fields' => [
            'parts_per_day' => ['number', $L('Parts per day', 'Parts par jour', 'الحصص يوميا'), 'req' => true, 'min' => 1, 'maxv' => 1000],
            'macro_slug' => ['select', $L('Macro work', 'Macro-œuvre', 'العمل الكبير'), 'options' => $macros],
            'status' => ['select', $L('Status', 'Statut', 'الحالة'), 'options' => ['active' => 'active', 'paused' => 'paused', 'cancelled' => 'cancelled']],
            'next_charge_on' => ['date', $L('Next charge on', 'Prochain prélèvement', 'الخصم القادم')],
            'paused_until' => ['date', $L('Paused until', 'En pause jusqu’au', 'متوقف حتى')],
        ],
    ],
    'manager-applications' => [
        'table' => 'manager_applications', 'perm' => 'managers', 'wperm' => 'managers', 'actions' => ['update', 'delete'],
        'title' => $L('Manager application', 'Candidature de manager', 'طلب مدير'), 'order' => 'score DESC, id DESC',
        'list' => 'id, ref, full_name, email, macro_slug AS macro, years, team_size_led AS team, region_slug AS region, score, status, created_at',
        'fields' => [
            'full_name' => ['text', $L('Full name', 'Nom complet', 'الاسم الكامل'), 'req' => true, 'max' => 160],
            'email' => ['email', $L('E-mail', 'E-mail', 'البريد'), 'req' => true, 'max' => 160],
            'phone' => ['text', $L('Phone', 'Téléphone', 'الهاتف'), 'max' => 40, 'pattern' => '/^\+?[0-9 ().\-]{0,20}$/'],
            'macro_slug' => ['select', $L('Macro work', 'Macro-œuvre', 'العمل الكبير'), 'options' => $macros],
            'years' => ['number', $L('Years of experience', 'Années d’expérience', 'سنوات الخبرة'), 'min' => 0, 'maxv' => 80, 'default' => 0],
            'team_size_led' => ['number', $L('Team size led', 'Taille d’équipe dirigée', 'حجم الفريق الذي قاده'), 'min' => 0, 'maxv' => 100000, 'default' => 0],
            'score' => ['number', $L('Score', 'Score', 'النقاط'), 'min' => 0, 'maxv' => 1000, 'default' => 0],
            'motivation' => ['ro', $L('Motivation', 'Motivation', 'الدافع')],
            'status' => ['select', $L('Status', 'Statut', 'الحالة'), 'options' => ['received' => 'received', 'shortlisted' => 'shortlisted', 'interview' => 'interview', 'accepted' => 'accepted', 'declined' => 'declined']],
        ],
    ],
    'inbox' => [
        'table' => 'contact_messages', 'perm' => 'messages', 'wperm' => 'messages', 'actions' => ['update', 'delete'],
        'title' => $L('Message', 'Message', 'رسالة'), 'order' => 'id DESC',
        'list' => 'id, desk, name, email, subject, substr(body,1,160) AS message, status, created_at',
        'fields' => [
            'name' => ['ro', $L('From', 'De', 'من')],
            'email' => ['ro', $L('E-mail', 'E-mail', 'البريد')],
            'subject' => ['ro', $L('Subject', 'Sujet', 'الموضوع')],
            'body' => ['ro', $L('Message', 'Message', 'الرسالة')],
            'status' => ['select', $L('Status', 'Statut', 'الحالة'), 'options' => ['new' => 'new', 'read' => 'read', 'answered' => 'answered', 'archived' => 'archived']],
        ],
    ],
    'communications' => [
        'table' => 'newsletter_subscribers', 'perm' => 'messages', 'wperm' => 'communications', 'actions' => ['create', 'update', 'delete'],
        'title' => $L('Newsletter subscriber', 'Abonné à la lettre', 'مشترك في النشرة'), 'order' => 'id DESC',
        'list' => "id, email, lang, CASE WHEN unsubscribed_at IS NOT NULL OR lower(email) IN (SELECT lower(email) FROM mail_suppressions) THEN 'unsubscribed' ELSE 'active' END AS state, COALESCE(unsubscribed_at, '') AS unsubscribed_at, created_at",
        'fields' => [
            'email' => ['email', $L('E-mail (set once)', 'E-mail (saisi une fois)', 'البريد (يُدخل مرة واحدة)'), 'req' => true, 'max' => 160, 'create_only' => true],
            'lang' => ['select', $L('Language', 'Langue', 'اللغة'), 'options' => ['en' => 'en', 'fr' => 'fr', 'ar' => 'ar']],
            'unsubscribed_at' => ['date', $L('Unsubscribed on (empty = subscribed)', 'Désabonné le (vide = abonné)', 'ألغى الاشتراك في (فارغ = مشترك)')],
        ],
    ],
    'mail-outbox' => [
        // The send log: entries can be removed (housekeeping) but never created or edited by hand.
        'table' => 'mail_outbox', 'no_country' => true, 'perm' => 'messages', 'wperm' => 'mail-outbox', 'actions' => ['delete'],
        'title' => $L('Mail log entry', 'Entrée du journal d’envoi', 'سجل رسالة'), 'order' => 'id DESC',
        'list' => 'id, to_email AS "to", template, lang, driver, status, substr(COALESCE(error, \'\'),1,120) AS error, created_at',
        'fields' => [],
    ],

    // ------------------------------------------------------------------ new menus
    'contracts' => [
        'table' => 'contracts', 'perm' => 'clients', 'wperm' => 'contracts', 'actions' => ['create', 'update', 'delete'],
        'in_use' => ['SELECT COUNT(*) FROM invoices WHERE contract_id = ?'],
        'title' => $L('Contract', 'Contrat', 'عقد'), 'order' => 'id DESC',
        'list' => 'id, (SELECT c.name FROM clients c WHERE c.id = contracts.client_id) AS client, substr(COALESCE(scope, \'\'),1,80) AS scope, hours, status, created_at',
        'fields' => [
            'client_id' => ['select', $L('Client', 'Client', 'العميل'), 'req' => true, 'options' => $clientsPick],
            'scope' => ['textarea', $L('Scope of work', 'Périmètre', 'نطاق العمل'), 'max' => 2000],
            'hours' => ['number', $L('Hours', 'Heures', 'الساعات'), 'min' => 0, 'maxv' => 1000000, 'default' => 0],
            'status' => ['select', $L('Status', 'Statut', 'الحالة'), 'options' => ['draft' => 'draft', 'active' => 'active', 'completed' => 'completed', 'cancelled' => 'cancelled']],
        ],
    ],
    'invoices' => [
        'table' => 'invoices', 'perm' => 'payroll', 'wperm' => 'invoices', 'actions' => ['create', 'update', 'delete'],
        'title' => $L('Invoice', 'Facture', 'فاتورة'), 'order' => 'id DESC',
        'list' => 'id, contract_id AS contract, amount, status, created_at',
        'fields' => [
            'contract_id' => ['select', $L('Contract', 'Contrat', 'العقد'), 'options' => $contractsPick],
            'amount' => ['number', $L('Amount', 'Montant', 'المبلغ'), 'min' => 0, 'maxv' => 1000000000, 'step' => '0.01', 'default' => 0],
            'status' => ['select', $L('Status', 'Statut', 'الحالة'), 'options' => ['draft' => 'draft', 'sent' => 'sent', 'paid' => 'paid', 'void' => 'void']],
        ],
    ],
    'directives' => [
        'table' => 'directives', 'perm' => 'weekly', 'wperm' => 'directives', 'actions' => ['create', 'update', 'delete'],
        'on_create' => fn(array $v, int $uid) => ['created_by' => $uid],
        'title' => $L('Weekly directive', 'Directive hebdomadaire', 'توجيه أسبوعي'), 'order' => 'id DESC',
        'list' => 'id, week_id AS week, service_slug AS service, substr(body,1,120) AS directive, created_by AS by_user, created_at',
        'fields' => [
            'week_id' => ['text', $L('Week (YYYY-Www)', 'Semaine (AAAA-Sss)', 'الأسبوع (YYYY-Www)'), 'req' => true, 'max' => 8, 'pattern' => '/^\d{4}-W(0[1-9]|[1-4]\d|5[0-3])$/'],
            'service_slug' => ['select', $L('Service (empty = all)', 'Service (vide = tous)', 'الخدمة (فارغ = الكل)'), 'options' => $services],
            'body' => ['textarea', $L('Directive', 'Directive', 'التوجيه'), 'req' => true, 'max' => 4000],
        ],
    ],
];
