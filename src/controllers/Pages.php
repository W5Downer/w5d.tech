<?php
declare(strict_types=1);

namespace W5D\Controllers;

use W5D\{Content, Request, Response, ViewEnv, Variables, Auth};

/** Public pages. JSON-driven pages render through the generic view; entity pages are assembled from content. */
final class Pages
{
    /** tri-lingual literal helper for builder-authored copy */
    public static function L(string $en, string $fr, string $ar): array { return ['en' => $en, 'fr' => $fr, 'ar' => $ar]; }

    public static function render(Request $q, array $page, int $status = 200): Response
    {
        ViewEnv::share('canonicalPath', $q->path === '/' ? '/' : rtrim($q->path, '/') . '/');
        \W5D\Seo::page(['sections' => $page['sections'] ?? [], 'title_short' => t($page['title'] ?? '')]);
        $html = view('pages.page', [
            'title' => t($page['title'] ?? 'W5D') . ' — W5D',
            'description' => t($page['description'] ?? ''),
            'robots' => $page['robots'] ?? 'index,follow',
            'sections' => $page['sections'] ?? [],
        ]);
        return Response::html($html, $status);
    }

    public static function notFound(Request $q): Response
    {
        ViewEnv::share('canonicalPath', $q->path);
        return not_found();
    }

    public static function home(Request $q): Response { return self::page($q, 'home'); }

    public static function page(Request $q, string $slug): Response
    {
        $slug = str_replace('/', '-', trim($slug, '/'));
        $p = Content::page($slug);
        return $p ? self::render($q, $p) : self::notFound($q);
    }

    public const AUDIENCES = ['owner', 'admin', 'leader', 'member', 'investor', 'customer', 'user'];
    /** Doors that belong to the team: visible only to a signed-in person of that role (the owner sees all three). Everyone else gets a 404. */
    public const PRIVATE_AUDIENCES = ['owner', 'admin', 'leader'];

    /** May the current visitor see this audience door? Public doors: always. Team doors: only the matching signed-in role. */
    public static function canSeeAudience(string $a): bool
    {
        if (!in_array($a, self::PRIVATE_AUDIENCES, true)) return in_array($a, self::AUDIENCES, true);
        $r = Auth::check() ? Auth::role() : 'guest';
        if ($r === 'owner') return true;
        return $a === 'leader' ? in_array($r, ['leader', 'manager'], true) : $r === $a;
    }

    /** @return list<string> the doors the current visitor may see (hub, quick-find, aside). */
    public static function visibleAudiences(): array { return array_values(array_filter(self::AUDIENCES, [self::class, 'canSeeAudience'])); }

    /** Audience homepage /for/{audience}/ (owner, admin, leader, member, investor, customer, user). */
    public static function audience(Request $q): Response
    {
        $a = (string)($q->params['aud'] ?? '');
        if (!in_array($a, self::AUDIENCES, true) || !self::canSeeAudience($a)) return self::notFound($q);
        $r = self::page($q, 'for-' . $a);
        return in_array($a, self::PRIVATE_AUDIENCES, true) ? $r->withHeaders(['X-Robots-Tag' => 'noindex, nofollow', 'Cache-Control' => 'no-store, private']) : $r;
    }

    public static function trust(Request $q): Response { return self::page($q, 'trust-' . ($q->params['page'] ?? '')); }

    /* ---------- macro works ---------- */
    public static function macroIndex(Request $q): Response
    {
        $L = [self::class, 'L'];
        return self::render($q, ['title' => $L('Five macro works', 'Cinq macro-œuvres', 'خمسة أعمال كبرى'),
            'description' => $L('Five domains, {{ counts.services }} services, one weekly work engine.', 'Cinq domaines, {{ counts.services }} services, un moteur de travail hebdomadaire.', 'خمسة مجالات و{{ counts.services }} خدمة ومحرك عمل أسبوعي واحد.'),
            'sections' => [
                ['type' => 'hero', 'id' => 'mw-index-hero', 'variant' => 'editorial', 'gradient' => 'focus',
                    'eyebrow' => $L('Macro works', 'Macro-œuvres', 'الأعمال الكبرى'),
                    'title' => $L('Five domains. **One engine.**', 'Cinq domaines. **Un moteur.**', 'خمسة مجالات. **محرك واحد.**'),
                    'lead' => $L('Each macro work groups {{ experts.services_per_macro }} services. Each service is a weekly package of reviewable work.', 'Chaque macro-œuvre regroupe {{ experts.services_per_macro }} services. Chaque service est un lot hebdomadaire de travail vérifiable.', 'يجمع كل عمل كبير {{ experts.services_per_macro }} خدمة. وكل خدمة حزمة أسبوعية من العمل القابل للمراجعة.')],
                ['type' => 'macro-cards', 'id' => 'mw-index-cards', 'tier' => 'e', 'focus' => 3],
                ['type' => 'cta', 'id' => 'mw-index-cta', 'gradient' => 'peak', 'title' => $L('Fund a path in the domain you believe in.', 'Financez un parcours dans le domaine auquel vous croyez.', 'موّل مسارا في المجال الذي تؤمن به.'),
                    'ctas' => [['label' => $L('Take a part today', 'Prendre une part', 'اشتر حصة اليوم'), 'href' => '/checkout/', 'style' => 'peak', 'arrow' => true]]],
            ]]);
    }

    public static function macro(Request $q): Response
    {
        $m = Content::get('macros', (string)($q->params['slug'] ?? ''));
        if (!$m) return self::notFound($q);
        $L = [self::class, 'L'];
        $g = $m['slug'];
        $svcs = Content::servicesOf($g);
        $projects = array_values(array_filter(array_map(fn($p) => $p['slug'], array_filter(Content::all('projects'), fn($p) => array_intersect($p['services'] ?? [], array_keys($svcs))))));
        $sections = [
            ['type' => 'slider', 'id' => 'mw-hero', 'source' => 'macro-' . $g],
            ['type' => 'prose', 'id' => 'mw-why', 'tier' => 'i', 'focus' => 2, 'eyebrow' => $L('Why it matters', 'Pourquoi c’est important', 'لماذا يهم'), 'title' => $m['tagline'],
                'paragraphs' => array_merge($m['body'] ?? [], [$m['why'] ?? ''])],
            ['type' => 'definitions', 'id' => 'mw-scope', 'tier' => 'i', 'focus' => 2, 'title' => $L('Scope and boundary', 'Périmètre et limites', 'النطاق والحدود'), 'items' => [
                ['term' => $L('Guiding question', 'Question directrice', 'السؤال الموجِّه'), 'def' => $m['question']],
                ['term' => $L('Fields', 'Disciplines', 'التخصصات'), 'def' => $m['fields']],
                ['term' => $L('Unit of work', 'Unité de travail', 'وحدة العمل'), 'def' => $m['unit']],
                ['term' => $L('Boundary', 'Limite', 'الحد'), 'def' => $m['boundary']],
                ['term' => $L('Feeds', 'Alimente', 'يغذّي'), 'def' => $m['feeds']],
                ['term' => $L('Service range', 'Plage de services', 'نطاق الخدمات'), 'def' => $m['range'] . ' · ' . count($svcs)],
            ]],
            ['type' => 'service-catalog', 'id' => 'mw-services', 'tier' => 'i', 'focus' => 3, 'macro' => $g,
                'title' => $L('The services of this macro work', 'Les services de cette macro-œuvre', 'خدمات هذا العمل الكبير'),
                'lead' => $L('Every service has its own page: deliverables, evidence, limits.', 'Chaque service a sa page : livrables, preuves, limites.', 'لكل خدمة صفحتها: المخرجات والأدلة والحدود.')],
        ];
        if ($projects) $sections[] = ['type' => 'project-cards', 'id' => 'mw-projects', 'tier' => 'i', 'focus' => 2, 'only' => $projects, 'limit' => 6, 'title' => $L('Projects that use these services', 'Projets qui utilisent ces services', 'مشاريع تستخدم هذه الخدمات')];
        $sections[] = ['type' => 'cta', 'id' => 'mw-cta', 'gradient' => $g,
            'title' => $L('Take a part in **' . t($m['name'], 'en') . '**', 'Prenez une part dans **' . t($m['name'], 'fr') . '**', 'خذ حصة في **' . t($m['name'], 'ar') . '**'),
            'body' => $L('A part funds certification and delivery capacity in this domain. It is an investment in a path, not a job.', 'Une part finance la certification et la capacité de livraison dans ce domaine. C’est un investissement dans un parcours, pas un emploi.', 'تمول الحصة الاعتماد وقدرة التنفيذ في هذا المجال. هي استثمار في مسار لا وظيفة.'),
            'ctas' => [['label' => $L('Take a part today', 'Prendre une part', 'اشتر حصة اليوم'), 'href' => '/checkout/?macro=' . $g, 'style' => 'peak', 'arrow' => true], ['label' => $L('Apply as manager', 'Candidater comme manager', 'قدّم كمدير'), 'href' => '/careers/managers/?macro=' . $g, 'style' => 'ghost']]];
        $sections[] = ['type' => 'sticky-cta', 'id' => 'sticky', 'href' => '/checkout/?macro=' . $g, 'label' => $L('Take a part today', 'Prendre une part', 'اشتر حصة اليوم')];
        return self::render($q, ['title' => $m['name'], 'description' => $m['tagline'], 'sections' => $sections]);
    }

    /* ---------- services ---------- */
    public static function serviceIndex(Request $q): Response
    {
        $L = [self::class, 'L'];
        return self::render($q, ['title' => $L('{{ counts.services }} services', '{{ counts.services }} services', '{{ counts.services }} خدمة'),
            'description' => $L('The full service catalogue: five macro works, {{ counts.services }} weekly work packages.', 'Le catalogue complet : cinq macro-œuvres, {{ counts.services }} lots de travail hebdomadaires.', 'الفهرس الكامل: خمسة أعمال كبرى و{{ counts.services }} حزمة عمل أسبوعية.'),
            'sections' => [
                ['type' => 'hero', 'id' => 'svc-index-hero', 'variant' => 'editorial', 'gradient' => 'focus', 'eyebrow' => $L('Service catalogue', 'Catalogue de services', 'فهرس الخدمات'),
                    'title' => $L('{{ counts.services }} services. **Each one reviewable.**', '{{ counts.services }} services. **Chacun vérifiable.**', '{{ counts.services }} خدمة. **كل واحدة قابلة للمراجعة.**'),
                    'lead' => $L('Search by name. Every page lists deliverables, evidence and declared limits.', 'Recherchez par nom. Chaque page liste livrables, preuves et limites déclarées.', 'ابحث بالاسم. تعرض كل صفحة المخرجات والأدلة والحدود المعلنة.')],
                ['type' => 'service-catalog', 'id' => 'svc-index-list', 'tier' => 'i', 'focus' => 3],
            ]]);
    }

    public static function service(Request $q): Response
    {
        $s = Content::get('services', (string)($q->params['slug'] ?? ''));
        if (!$s) return self::notFound($q);
        $L = [self::class, 'L'];
        $macro = Content::get('macros', $s['macro']) ?? ['name' => '', 'slug' => $s['macro']];
        $g = $s['macro'];
        $id3 = str_pad((string)$s['id'], 3, '0', STR_PAD_LEFT);
        $slug = $s['slug'];
        $long = $s['long'] ?? [];
        $all = Content::all('services');
        $rel = function (array $refs) use ($all) {
            $out = [];
            foreach ($refs as $r) {
                $x = $all[$r['slug']] ?? null;
                if ($x) $out[] = ['title' => $x['title'], 'body' => $x['short'], 'href' => '/services/' . $x['slug'] . '/', 'tag' => '#' . str_pad((string)$x['id'], 3, '0', STR_PAD_LEFT), 'icon' => 'link'];
            }
            return $out;
        };
        $projects = array_keys(array_filter(Content::all('projects'), fn($p) => in_array($slug, $p['services'] ?? [], true)));
        $apply = '/app/register/?service=' . $slug;

        $secs = [];
        $secs[] = ['type' => 'hero', 'id' => 'svc-hero', 'variant' => 'classic', 'gradient' => $g, 'icon' => 'layers',
            'eyebrow' => '#' . $id3 . ' · ' . t($macro['name'], 'en') . ' · ' . ($s['placement'] ?? ''),
            'title' => $s['title'], 'lead' => $s['abstract'],
            'ctas' => [['label' => $L('Apply to this service', 'Candidater à ce service', 'قدّم لهذه الخدمة'), 'href' => $apply, 'style' => 'peak', 'arrow' => true], ['label' => $L('Check my eligibility', 'Vérifier mon éligibilité', 'تحقق من أهليتي'), 'href' => '/join/eligibility/?service=' . $slug, 'style' => 'ghost']],
            'badges' => [['icon' => 'users', 'label' => $L('Cohort ' . ($s['cohort'] ?? ''), 'Cohorte ' . ($s['cohort'] ?? ''), 'دفعة ' . ($s['cohort'] ?? ''))], ['icon' => 'graduation-cap', 'label' => ['en' => (string)($s['diploma'] ?? '')]]],
            'note' => $L('A part is an investment in a path, not a job. No employment is promised.', 'Une part est un investissement dans un parcours, pas un emploi. Aucun emploi n’est promis.', 'الحصة استثمار في مسار لا وظيفة. لا يُعد بأي توظيف.')];
        if (!empty($long[1])) $secs[] = ['type' => 'prose', 'id' => 'svc-problem', 'tier' => 'i', 'focus' => 2, 'eyebrow' => $L('The problem', 'Le problème', 'المشكلة'), 'title' => $L('Why this service exists', 'Pourquoi ce service existe', 'لماذا توجد هذه الخدمة'), 'paragraphs' => [$long[1]]];
        $secs[] = ['type' => 'definitions', 'id' => 'svc-fit', 'tier' => 'i', 'focus' => 2, 'title' => $L('Who fits', 'Pour qui', 'من يناسبه'), 'items' => [
            ['term' => $L('Fields', 'Disciplines', 'التخصصات'), 'def' => $s['fields']],
            ['term' => $L('Diploma', 'Diplôme', 'الشهادة'), 'def' => ['en' => (string)$s['diploma']]],
            ['term' => $L('Cohort size', 'Taille de cohorte', 'حجم الدفعة'), 'def' => ['en' => (string)$s['cohort']]],
            ['term' => $L('Regions', 'Régions', 'الجهات'), 'def' => $s['region']],
        ], 'ctas' => [['label' => $L('Check my eligibility', 'Vérifier mon éligibilité', 'تحقق من أهليتي'), 'href' => '/join/eligibility/?service=' . $slug, 'style' => 'focus']]];
        $secs[] = ['type' => 'steps', 'id' => 'svc-path', 'tier' => 'i', 'focus' => 3, 'title' => $L('Your path', 'Votre parcours', 'مسارك'), 'items' => [
            ['title' => $L('Register', 'S’inscrire', 'التسجيل'), 'body' => $L('Create your account with your identity details.', 'Créez votre compte avec vos données d’identité.', 'أنشئ حسابك ببيانات هويتك.')],
            ['title' => $L('Verify', 'Vérifier', 'التحقق'), 'body' => $L('Diploma and identity are checked by a human reviewer.', 'Diplôme et identité sont contrôlés par un relecteur humain.', 'يراجع مراجع بشري الشهادة والهوية.')],
            ['title' => $L('Certify', 'Certifier', 'الاعتماد'), 'body' => $L('Complete the academy submissions for this service.', 'Réalisez les travaux de l’académie pour ce service.', 'أنجز مهام الأكاديمية لهذه الخدمة.')],
            ['title' => $L('Weekly work', 'Travail hebdomadaire', 'العمل الأسبوعي'), 'body' => $L('Submit one reviewable report per week; {{ quality.sampling_percent }}% are sampled.', 'Remettez un rapport vérifiable par semaine ; {{ quality.sampling_percent }} % sont échantillonnés.', 'قدّم تقريرا قابلا للمراجعة أسبوعيا؛ تُعاين {{ quality.sampling_percent }}٪ منها.')],
        ]];
        if (!empty($long[0])) $secs[] = ['type' => 'prose', 'id' => 'svc-tasks', 'tier' => 'i', 'focus' => 2, 'title' => $L('What the work looks like', 'À quoi ressemble le travail', 'كيف يبدو العمل'), 'paragraphs' => [$long[0]]];
        $secs[] = ['type' => 'definitions', 'id' => 'svc-week', 'tier' => 'i', 'focus' => 2, 'title' => $L('Evidence and weekly thresholds', 'Preuves et seuils hebdomadaires', 'الأدلة والعتبات الأسبوعية'), 'items' => [
            ['term' => $L('Acceptance evidence', 'Preuve d’acceptation', 'دليل القبول'), 'def' => $s['evidence']],
            ['term' => $L('Proposed thresholds', 'Seuils proposés', 'العتبات المقترحة'), 'def' => $s['kpi']],
        ]];
        $secs[] = ['type' => 'bars', 'id' => 'svc-seats', 'tier' => 'i', 'focus' => 3, 'title' => $L('Seats today', 'Places aujourd’hui', 'المقاعد اليوم'),
            'lead' => $L('The design target is {{ experts.per_service }} experts per service. Certified today is shown honestly.', 'La cible de conception est de {{ experts.per_service }} experts par service. Le nombre certifié aujourd’hui est affiché honnêtement.', 'هدف التصميم {{ experts.per_service }} خبير لكل خدمة. ويُعرض العدد المعتمد اليوم بصدق.'),
            'items' => [['label' => $L('Certified experts', 'Experts certifiés', 'الخبراء المعتمدون'), 'value' => '{{ d.experts_current }}', 'max' => '{{ experts.per_service }}', 'note' => $L('Programme-wide figure, as of {{ dates.as_of }}.', 'Chiffre global du programme, au {{ dates.as_of }}.', 'رقم البرنامج الإجمالي، حتى {{ dates.as_of }}.')]]];
        $secs[] = ['type' => 'cta', 'id' => 'svc-mid-cta', 'gradient' => $g, 'title' => $L('Fund the path for **' . t($s['title'], 'en') . '**', 'Financez le parcours pour **' . t($s['title'], 'fr') . '**', 'موّل مسار **' . t($s['title'], 'ar') . '**'),
            'ctas' => [['label' => $L('Take a part today', 'Prendre une part', 'اشتر حصة اليوم'), 'href' => '/checkout/?macro=' . $g, 'style' => 'peak', 'arrow' => true]]];
        $rows = [];
        foreach ($s['deliverables'] ?? [] as $d) $rows[] [] = null;
        $rows = array_map(fn($d) => [$d['name'], $d['unit'], ['en' => number_format((float)$d['target'])]], $s['deliverables'] ?? []);
        $secs[] = ['type' => 'table', 'id' => 'svc-delivery', 'tier' => 'i', 'focus' => 2, 'title' => $L('Pilot deliverables per week', 'Livrables pilotes par semaine', 'مخرجات تجريبية أسبوعيا'),
            'columns' => [$L('Deliverable', 'Livrable', 'المخرج'), $L('Unit', 'Unité', 'الوحدة'), $L('Target', 'Cible', 'الهدف')], 'rows' => $rows,
            'note' => !empty($long[2]) ? $long[2] : null];
        $secs[] = ['type' => 'notice', 'id' => 'svc-limits', 'title' => $L('Limits of this service', 'Limites de ce service', 'حدود هذه الخدمة'), 'items' => array_merge([$s['boundary']], array_slice($s['risks'] ?? [], 0, 3))];
        if ($projects) $secs[] = ['type' => 'project-cards', 'id' => 'svc-projects', 'tier' => 'i', 'focus' => 2, 'only' => $projects, 'limit' => 3, 'title' => $L('Projects using this service', 'Projets utilisant ce service', 'مشاريع تستخدم هذه الخدمة')];
        $relItems = array_merge($rel($s['depends'] ?? []), $rel($s['feeds'] ?? []));
        if ($relItems) $secs[] = ['type' => 'cards', 'id' => 'svc-related', 'tier' => 'i', 'focus' => 2, 'cols' => 3, 'title' => $L('Related services', 'Services liés', 'خدمات ذات صلة'), 'items' => $relItems];
        $secs[] = ['type' => 'faq', 'id' => 'svc-faq', 'tier' => 'i', 'focus' => 2, 'title' => $L('Questions', 'Questions', 'أسئلة'), 'items' => [
            ['q' => $L('Is this a job?', 'Est-ce un emploi ?', 'هل هذه وظيفة؟'), 'a' => ['en' => \W5D\Lang::line('limits.jobs', 'en'), 'fr' => \W5D\Lang::line('limits.jobs', 'fr'), 'ar' => \W5D\Lang::line('limits.jobs', 'ar')]],
            ['q' => $L('How many experts exist today?', 'Combien d’experts existent aujourd’hui ?', 'كم عدد الخبراء اليوم؟'), 'a' => $L('Certified: {{ d.experts_current|first }}. Delivery team: {{ experts.developers_today|devs }}. {{ d.experts_target }} is a design equation.', 'Certifiés : {{ d.experts_current|first }}. Équipe de livraison : {{ experts.developers_today|devs }}. {{ d.experts_target }} est une équation de conception.', 'المعتمدون: {{ d.experts_current|first }}. فريق التسليم: {{ experts.developers_today|devs }}. {{ d.experts_target }} معادلة تصميم.')],
            ['q' => $L('How is quality checked?', 'Comment la qualité est-elle contrôlée ?', 'كيف تُراقَب الجودة؟'), 'a' => $L('Managers review every report; a fixed {{ quality.sampling_percent }}% random audit rechecks evidence.', 'Les managers relisent chaque rapport ; un audit aléatoire fixe de {{ quality.sampling_percent }} % revérifie les preuves.', 'يراجع المديرون كل تقرير؛ وتدقيق عشوائي ثابت بنسبة {{ quality.sampling_percent }}٪ يعيد فحص الأدلة.')],
        ]];
        $secs[] = ['type' => 'cta', 'id' => 'svc-final', 'gradient' => 'peak', 'title' => $L('Ready to start?', 'Prêt à commencer ?', 'مستعد للبدء؟'),
            'ctas' => [['label' => $L('Apply to this service', 'Candidater à ce service', 'قدّم لهذه الخدمة'), 'href' => $apply, 'style' => 'peak', 'arrow' => true], ['label' => $L('Become a manager', 'Devenir manager', 'كن مديرا'), 'href' => '/careers/managers/?macro=' . $g, 'style' => 'ghost']]];
        $secs[] = ['type' => 'sticky-cta', 'id' => 'sticky', 'href' => $apply, 'label' => $L('Apply to this service', 'Candidater à ce service', 'قدّم لهذه الخدمة')];
        return self::render($q, ['title' => $s['title'], 'description' => $s['short'], 'sections' => $secs]);
    }

    /* ---------- projects ---------- */
    public static function projectIndex(Request $q): Response
    {
        $L = [self::class, 'L'];
        return self::render($q, ['title' => $L('Projects', 'Projets', 'المشاريع'),
            'description' => $L('{{ counts.projects }} projects with dated status: live, building or concept.', '{{ counts.projects }} projets avec statut daté : en ligne, en construction ou concept.', '{{ counts.projects }} مشروعا بحالة مؤرخة: قائم أو قيد البناء أو فكرة.'),
            'sections' => [
                ['type' => 'slider', 'id' => 'prj-index-hero', 'source' => 'projects'],
                ['type' => 'project-cards', 'id' => 'prj-index-list', 'tier' => 'i', 'focus' => 3, 'limit' => 100, 'title' => $L('Every project, with its honest status', 'Chaque projet, avec son statut honnête', 'كل مشروع بحالته الصادقة')],
            ]]);
    }

    public static function project(Request $q): Response
    {
        $p = Content::get('projects', (string)($q->params['slug'] ?? ''));
        if (!$p) return self::notFound($q);
        $L = [self::class, 'L'];
        $svc = Content::all('services');
        $cards = [];
        foreach ($p['services'] ?? [] as $sl) if (isset($svc[$sl])) $cards[] = ['title' => $svc[$sl]['title'], 'body' => $svc[$sl]['short'], 'href' => '/services/' . $sl . '/', 'tag' => '#' . str_pad((string)$svc[$sl]['id'], 3, '0', STR_PAD_LEFT), 'icon' => 'layers'];
        $secs = [
            ['type' => 'hero', 'id' => 'prj-hero', 'variant' => 'classic', 'gradient' => $p['gradient'] ?? 'focus', 'icon' => $p['icon'] ?? 'folder',
                'eyebrow' => ['en' => t($p['status_label'], 'en') . ' · ' . ($p['status_date'] ?? ''), 'fr' => t($p['status_label'], 'fr') . ' · ' . ($p['status_date'] ?? ''), 'ar' => t($p['status_label'], 'ar') . ' · ' . ($p['status_date'] ?? '')],
                'title' => $p['name'], 'lead' => $p['tagline'],
                'ctas' => [['label' => $L('Take a part today', 'Prendre une part', 'اشتر حصة اليوم'), 'href' => '/checkout/', 'style' => 'peak', 'arrow' => true], ['label' => $L('All projects', 'Tous les projets', 'كل المشاريع'), 'href' => '/projects/', 'style' => 'ghost']]],
            ['type' => 'prose', 'id' => 'prj-outcome', 'tier' => 'i', 'focus' => 2, 'title' => $L('What this project is', 'Ce qu’est ce projet', 'ما هو هذا المشروع'), 'paragraphs' => $p['outcome'] ?? []],
            ['type' => 'definitions', 'id' => 'prj-evidence', 'tier' => 'i', 'focus' => 2, 'title' => $L('Status and evidence', 'Statut et preuves', 'الحالة والأدلة'), 'items' => array_values(array_filter([
                ['term' => $L('Status', 'Statut', 'الحالة'), 'def' => $p['status_label']],
                ['term' => $L('Dated', 'Date', 'التاريخ'), 'def' => ['en' => (string)($p['status_date'] ?? '')]],
                ['term' => $L('Evidence', 'Preuve', 'الدليل'), 'def' => $p['evidence'] ?? ''],
                !empty($p['needs']) ? ['term' => $L('Needs', 'Besoins', 'الاحتياجات'), 'def' => $p['needs']] : null,
            ]))],
        ];
        if (!empty($p['timeline'])) $secs[] = ['type' => 'timeline', 'id' => 'prj-timeline', 'tier' => 'i', 'focus' => 2, 'title' => $L('Timeline', 'Chronologie', 'الجدول الزمني'), 'items' => $p['timeline']];
        if ($cards) $secs[] = ['type' => 'cards', 'id' => 'prj-services', 'tier' => 'i', 'focus' => 2, 'cols' => 3, 'title' => $L('Services this project draws on', 'Services mobilisés', 'الخدمات التي يعتمد عليها'), 'items' => $cards];
        $secs[] = ['type' => 'notice', 'id' => 'prj-limits'];
        $secs[] = ['type' => 'cta', 'id' => 'prj-final', 'gradient' => 'peak', 'title' => $L('Back this path with a daily part', 'Soutenez ce parcours avec une part par jour', 'ادعم هذا المسار بحصة يومية'),
            'ctas' => [['label' => $L('Daily subscription', 'Abonnement quotidien', 'اشتراك يومي'), 'href' => '/subscription/', 'style' => 'peak', 'arrow' => true]]];
        return self::render($q, ['title' => $p['name'], 'description' => $p['tagline'], 'sections' => $secs]);
    }

    /* ---------- governorates ---------- */
    public static function governorate(Request $q): Response
    {
        $slug = (string)($q->params['gov'] ?? '');
        $reg = null;
        foreach (Content::file('countries/tn.json')['regions'] ?? [] as $r) if ($r['slug'] === $slug) $reg = $r;
        if (!$reg) return self::notFound($q);
        $L = [self::class, 'L'];
        $n = fn(string $l) => $reg['name'][$l] ?? $reg['name']['en'];
        return self::render($q, ['title' => $reg['name'],
            'description' => $L('W5D in ' . $n('en') . ': eligibility, services and how to register.', 'W5D à ' . $n('fr') . ' : éligibilité, services et inscription.', 'W5D في ' . $n('ar') . ': الأهلية والخدمات وكيفية التسجيل.'),
            'sections' => [
                ['type' => 'hero', 'id' => 'gov-hero', 'variant' => 'classic', 'gradient' => 'calm', 'icon' => 'map-pin', 'eyebrow' => $L('Tunisia hub', 'Pôle Tunisie', 'مركز تونس'),
                    'title' => $L('W5D in **' . $n('en') . '**', 'W5D à **' . $n('fr') . '**', 'W5D في **' . $n('ar') . '**'),
                    'lead' => $L('Remote-first, reviewable work for graduates of ' . $n('en') . '. Register to be counted in the public rollups.', 'Un travail à distance, vérifiable, pour les diplômés de ' . $n('fr') . '. Inscrivez-vous pour être compté dans les agrégats publics.', 'عمل عن بعد قابل للمراجعة لخريجي ' . $n('ar') . '. سجّل لتُحتسب ضمن المؤشرات العامة.'),
                    'ctas' => [['label' => $L('Register now', 'S’inscrire', 'سجّل الآن'), 'href' => '/app/register/?region=' . $slug, 'style' => 'peak', 'arrow' => true], ['label' => $L('Check my eligibility', 'Vérifier mon éligibilité', 'تحقق من أهليتي'), 'href' => '/join/eligibility/', 'style' => 'ghost']]],
                ['type' => 'macro-cards', 'id' => 'gov-macros', 'tier' => 'e', 'focus' => 3, 'title' => $L('Five macro works open to ' . $n('en'), 'Cinq macro-œuvres ouvertes à ' . $n('fr'), 'خمسة أعمال كبرى متاحة في ' . $n('ar'))],
                ['type' => 'gov-map', 'id' => 'gov-map', 'tier' => 'i', 'focus' => 2, 'title' => $L('Other governorates', 'Autres gouvernorats', 'ولايات أخرى')],
                ['type' => 'notice', 'id' => 'gov-limits'],
            ]]);
    }

    /* ---------- sitemap (human) ---------- */
    public static function sitemap(Request $q): Response
    {
        $L = [self::class, 'L'];
        $links = fn(array $a) => array_map(fn($x) => ['title' => $x[1], 'href' => $x[0], 'icon' => 'arrow-right'], $a);
        $core = [['/', $L('Home', 'Accueil', 'الرئيسية')], ['/for/', $L('Choose your door', 'Choisissez votre porte', 'اختر بابك')], ['/investors/', $L('Investors', 'Investisseurs', 'المستثمرون')], ['/pricing/', $L('Pricing', 'Tarification', 'الأسعار')], ['/checkout/', $L('Checkout', 'Paiement', 'الدفع')], ['/subscription/', $L('Daily subscription', 'Abonnement quotidien', 'اشتراك يومي')], ['/careers/managers/', $L('Become a manager', 'Devenir manager', 'كن مديرا')], ['/join/', $L('Join', 'Rejoindre', 'انضم')], ['/how-it-works/', $L('How it works', 'Comment ça marche', 'كيف يعمل')], ['/status/', $L('Status', 'Statut', 'الحالة')], ['/trust/', $L('Trust', 'Confiance', 'الثقة')], ['/trust/statement/', $L('Declared limits', 'Limites déclarées', 'الحدود المعلنة')], ['/trust/sampling/', $L('Sampling', 'Échantillonnage', 'العيّنات')], ['/trust/editorial/', $L('Editorial policy', 'Politique éditoriale', 'السياسة التحريرية')], ['/trust/corrections/', $L('Corrections', 'Corrections', 'التصويبات')], ['/trust/privacy/', $L('Privacy', 'Confidentialité', 'الخصوصية')], ['/trust/cookies/', $L('Cookies', 'Cookies', 'ملفات الارتباط')], ['/trust/data-rights/', $L('Your data rights', 'Vos droits sur les données', 'حقوقك في بياناتك')], ['/trust/terms/', $L('Terms', 'Conditions', 'الشروط')], ['/trust/refunds/', $L('Refunds', 'Remboursements', 'الاسترداد')], ['/trust/security/', $L('Security', 'Sécurité', 'الأمان')], ['/trust/accessibility/', $L('Accessibility', 'Accessibilité', 'إتاحة الوصول')], ['/trust/ads-policy/', $L('Advertising policy', 'Politique publicitaire', 'سياسة الإعلانات')], ['/trust/imprint/', $L('Imprint', 'Mentions légales', 'الإشعار القانوني')], ['/about/', $L('About', 'À propos', 'من نحن')], ['/contact/', $L('Contact', 'Contact', 'اتصل')], ['/developers/', $L('Developers', 'Développeurs', 'المطورون')], ['/agents/', $L('AI agents', 'Agents IA', 'وكلاء الذكاء الاصطناعي')]];
        $macros = array_map(fn($m) => ['title' => $m['name'], 'href' => '/macro-works/' . $m['slug'] . '/', 'icon' => $m['icon']], array_values(Content::all('macros')));
        $svcs = array_map(fn($s) => ['title' => $s['title'], 'href' => '/services/' . $s['slug'] . '/', 'icon' => 'layers'], array_values(Content::all('services')));
        $prjs = array_map(fn($p) => ['title' => $p['name'], 'href' => '/projects/' . $p['slug'] . '/', 'icon' => $p['icon'] ?? 'folder'], array_values(Content::all('projects')));
        return self::render($q, ['title' => $L('Sitemap', 'Plan du site', 'خريطة الموقع'), 'description' => $L('Every public page of W5D.', 'Toutes les pages publiques de W5D.', 'جميع صفحات W5D العامة.'), 'sections' => [
            ['type' => 'hero', 'id' => 'sm-hero', 'variant' => 'editorial', 'gradient' => 'calm', 'eyebrow' => $L('Sitemap', 'Plan du site', 'خريطة الموقع'), 'title' => $L('Every page, **one list**', 'Toutes les pages, **une liste**', 'كل الصفحات، **قائمة واحدة**')],
            ['type' => 'cards', 'id' => 'sm-core', 'cols' => 4, 'title' => $L('Main pages', 'Pages principales', 'الصفحات الرئيسية'), 'items' => $links($core)],
            ['type' => 'cards', 'id' => 'sm-macros', 'cols' => 3, 'title' => $L('Macro works', 'Macro-œuvres', 'الأعمال الكبرى'), 'items' => $macros],
            ['type' => 'cards', 'id' => 'sm-projects', 'cols' => 4, 'title' => $L('Projects', 'Projets', 'المشاريع'), 'items' => $prjs],
            ['type' => 'cards', 'id' => 'sm-services', 'cols' => 4, 'title' => $L('Services', 'Services', 'الخدمات'), 'items' => $svcs],
        ]]);
    }
}
