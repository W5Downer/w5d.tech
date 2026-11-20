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
        'table' => 'clients', 'perm' => 'clients', 'actions' => ['create', 'update', 'delete'],
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
        'table' => 'applicants', 'perm' => 'applicants', 'actions' => ['update', 'delete'],
        'title' => $L('Applicant', 'Candidat', 'مترشح'), 'order' => 'id DESC',
        'list' => 'id, full_name, email, macro_slug AS macro, service_slug AS service, diploma, status, created_at',
        'fields' => [
            'full_name' => ['text', $L('Full name', 'Nom complet', 'الاسم الكامل'), 'req' => true, 'max' => 160],
            'email' => ['email', $L('E-mail', 'E-mail', 'البريد'), 'max' => 160],
            'phone' => ['text', $L('Phone', 'Téléphone', 'الهاتف'), 'max' => 40, 'pattern' => '/^\+?[0-9 ().\-]{0,20}$/'],
            'diploma' => ['select', $L('Diploma', 'Diplôme', 'الشهادة'), 'options' => ['licence' => 'licence', 'maitrise' => 'maitrise', 'engineer' => 'engineer', 'phd' => 'phd', 'other' => 'other']],
            'status' => ['select', $L('Status', 'Statut', 'الحالة'), 'options' => ['registered' => 'registered', 'verified' => 'verified', 'certified' => 'certified', 'rejected' => 'rejected']],
        ],
    ],
    'verification' => [
        'table' => 'verifications', 'perm' => 'verification', 'actions' => ['update'],
        'title' => $L('Verification', 'Vérification', 'تحقق'), 'order' => 'id DESC',
        'list' => 'id, applicant_id AS applicant, kind, result, reviewer_id AS reviewer, created_at',
        'fields' => [
            'kind' => ['ro', $L('Kind', 'Type', 'النوع')],
            'result' => ['select', $L('Result', 'Résultat', 'النتيجة'), 'options' => ['pending' => 'pending', 'passed' => 'passed', 'failed' => 'failed']],
        ],
        'stamp' => ['reviewer_id' => 'uid'],
    ],
    'weekly' => [
        'table' => 'weekly_reports', 'perm' => 'weekly', 'actions' => ['update'], 'scope' => ['manager' => 'manager_id'],
        'title' => $L('Weekly report', 'Rapport hebdomadaire', 'تقرير أسبوعي'), 'order' => 'id DESC',
        'list' => 'id, week_id AS week, applicant_id AS member, service_slug AS service, units, status, substr(COALESCE(evidence, \'\'),1,100) AS evidence, created_at',
        'fields' => [
            'units' => ['number', $L('Units', 'Unités', 'الوحدات'), 'min' => 0, 'maxv' => 100000],
            'status' => ['select', $L('Status', 'Statut', 'الحالة'), 'options' => ['submitted' => 'submitted', 'approved' => 'approved', 'rejected' => 'rejected']],
            'evidence' => ['textarea', $L('Evidence', 'Preuves', 'الأدلة'), 'max' => 4000],
        ],
    ],
    'quality' => [
        'table' => 'cert_submissions', 'perm' => 'quality', 'actions' => ['update'],
        'title' => $L('Certification submission', 'Soumission de certification', 'طلب اعتماد'), 'order' => 'id DESC',
        'list' => 'id, applicant_id AS applicant, service_slug AS service, substr(COALESCE(task, \'\'),1,100) AS task, status, reviewer_id AS reviewer, created_at',
        'fields' => [
            'task' => ['ro', $L('Task', 'Tâche', 'المهمة')],
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
    'users' => [
        // password_hash and tokens are never selected, shown or written here.
        'table' => 'users', 'perm' => 'users', 'actions' => ['update'],
        'title' => $L('User', 'Utilisateur', 'مستخدم'), 'order' => 'id DESC',
        'list' => 'id, name, email, role_slug AS role, lang, active, CASE WHEN email_verified_at IS NULL THEN \'no\' ELSE \'yes\' END AS verified, COALESCE(last_login_at, \'\') AS last_login, created_at',
        'fields' => [
            'email' => ['ro', $L('E-mail', 'E-mail', 'البريد')],
            'name' => ['text', $L('Name', 'Nom', 'الاسم'), 'max' => 120],
            'role_slug' => ['select', $L('Role', 'Rôle', 'الدور'), 'req' => true, 'options' => ['admin' => 'admin', 'leader' => 'leader', 'manager' => 'manager', 'member' => 'member', 'investor' => 'investor', 'customer' => 'customer', 'user' => 'user']],
            'active' => ['bool', $L('Active', 'Actif', 'نشط'), 'default' => 1],
        ],
    ],
];
