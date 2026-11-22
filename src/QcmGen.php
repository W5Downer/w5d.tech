<?php
declare(strict_types=1);

namespace W5D;

/**
 * Generated QCM questions (no files, no storage).
 *
 * Every service has `qcm.generated_per_level` (default 1000) virtual questions at each of the 4 levels = 520,000 for 130 services.
 * Question number N of (service, level) is rebuilt on demand from the service's own data (deliverables, units, targets, risks and
 * their mitigations, boundary, dependencies, SDGs, fields, region) with a seed made of service + level + N, so it is always the
 * same question, in en/fr/ar, and a round (10 random questions) only builds the 10 it draws.
 *
 * Honest scope: the pool is wide in WORDING and DISTRACTORS but bounded in CONCEPTS (about 10-15 question kinds per level, each applied
 * to every fact of the service). It is a working bank that proves the engine and keeps rounds fresh; the expert-written bank
 * (docs/qcm-generation-prompt.md) is imported next to it and the two are drawn from together.
 *
 * Ids look like `service-slug.L2.g0417` (the "g" marks a generated question; stored questions keep `.017`).
 */
final class QcmGen
{
    public const LANGS = ['en', 'fr', 'ar'];

    /** Question kinds per level; a kind listed twice is drawn twice as often. Fact-driven kinds are listed more than the static ones. */
    private const SCHED = [
        1 => ['macro', 'del', 'del', 'notdel', 'unit', 'unit', 'all', 'fields', 'region', 'thr', 'cert', 'target', 'target', 'whosvc', 'whosvc'],
        2 => ['r2m', 'r2m', 'm2r', 'm2r', 'acc', 'dep', 'dep', 'feed', 'sdg', 'sdg', 'sdgt', 'weekly', 'leave', 'place'],
        3 => ['bound', 'bound', 'case', 'case', 'incomplete', 'links', 'rework', 'crit', 'tread', 'tread', 'unitrev', 'unitrev', 'kpi', 'kpi', 'nosource', 'nosource'],
        4 => ['samp', 'noperson', 'corr', 'income', 'refuse', 'm2rc', 'm2rc', 'breach', 'true', 'pressure', 'pressure', 'notrisk', 'notrisk'],
    ];

    private static array $S = [];
    private static array $pools = [];

    public static function enabled(): bool { return (int)Variables::get('qcm.generated', 1) === 1 && self::perLevel() > 0; }
    public static function perLevel(): int { return max(0, min(5000, (int)Variables::get('qcm.generated_per_level', 1000))); }
    public static function kinds(int $level): array { return array_values(array_unique(self::SCHED[$level] ?? [])); }

    /* ------------------------------------------------------------------ helpers */

    private static function tri(string $en, ?string $fr = null, ?string $ar = null): array { return ['en' => $en, 'fr' => $fr ?? $en, 'ar' => $ar ?? $en]; }

    private static function rnd(string $key): \Closure
    {
        $i = 0;
        return function (int $max) use ($key, &$i): int { return $max <= 1 ? 0 : (int)(hexdec(substr(hash('sha256', $key . '|' . $i++), 0, 10)) % $max); };
    }

    private static function shuf(array $a, \Closure $r): array
    {
        for ($i = count($a) - 1; $i > 0; $i--) { $j = $r($i + 1); [$a[$i], $a[$j]] = [$a[$j], $a[$i]]; }
        return $a;
    }

    private static function sub(array $a, int $n, \Closure $r): array { return array_slice(self::shuf($a, $r), 0, $n); }

    private static function cap(array $t): array
    {
        foreach (['en', 'fr'] as $l) $t[$l] = mb_strtoupper(mb_substr($t[$l], 0, 1)) . mb_substr($t[$l], 1);
        return $t;
    }

    private static function fill(array $tpl, array $vals): array
    {
        $out = [];
        foreach (self::LANGS as $l) {
            $m = [];
            foreach ($vals as $k => $v) $m['{' . $k . '}'] = is_array($v) ? $v[$l] : (string)$v;
            $out[$l] = strtr($tpl[$l], $m);
        }
        return $out;
    }

    private static function svc(string $slug): ?array
    {
        if (isset(self::$S[$slug])) return self::$S[$slug] ?: null;
        $s = Content::get('services', $slug);
        if (!$s) { self::$S[$slug] = []; return null; }
        $risks = [];
        foreach ($s['risks'] ?? [] as $x) {
            $c = $m = [];
            foreach (self::LANGS as $l) {
                $p = preg_split('/\s*[→←]\s*/u', (string)($x[$l] ?? ''), 2);
                if (count($p) !== 2 || trim($p[0]) === '' || trim($p[1]) === '') continue 2;
                $c[$l] = rtrim(trim($p[0]), '.'); $m[$l] = trim($p[1]);
            }
            $risks[] = ['cause' => $c, 'mit' => $m];
        }
        $macro = Content::get('macros', (string)($s['macro'] ?? ''));
        $s['_risks'] = $risks; $s['_macro'] = $macro['name'] ?? self::tri((string)($s['macro'] ?? ''));
        if (!$risks || count($s['deliverables'] ?? []) < 4) { self::$S[$slug] = []; return null; }
        return self::$S[$slug] = $s;
    }

    /** @return list<array{0:string,1:array}> [service slug, tri text] of one kind of fact, across all services */
    private static function pool(string $kind): array
    {
        if (isset(self::$pools[$kind])) return self::$pools[$kind];
        $out = [];
        foreach (Content::all('services') as $slug => $_) {
            $s = self::svc($slug);
            if (!$s) continue;
            $items = match ($kind) {
                'del' => array_map(fn($d) => $d['name'], $s['deliverables']),
                'unit' => array_map(fn($d) => $d['unit'], $s['deliverables']),
                'fields' => [$s['fields']], 'region' => [$s['region']], 'bound' => [self::cap($s['boundary'])], 'title' => [$s['title']],
                'cause' => array_map(fn($x) => $x['cause'], $s['_risks']), 'mit' => array_map(fn($x) => $x['mit'], $s['_risks']),
                default => [],
            };
            foreach ($items as $t) $out[] = [$slug, $t];
        }
        return self::$pools[$kind] = $out;
    }

    /** n texts of a pool, from other services, never equal (in English) to an avoided one. */
    private static function pick(string $pool, array $s, int $n, array $avoid, \Closure $r): array
    {
        $seen = [];
        foreach ($avoid as $a) $seen[$a['en']] = 1;
        $all = self::pool($pool); $cnt = count($all); $out = []; $guard = 0;
        while (count($out) < $n && $guard++ < 400 && $cnt) {
            [$slug, $t] = $all[$r($cnt)];
            if ($slug === $s['slug'] || isset($seen[$t['en']])) continue;
            $seen[$t['en']] = 1; $out[] = $t;
        }
        return $out;
    }

    private static function nums(int $t, \Closure $r): array
    {
        $c = [];
        foreach ([$t * 2, intdiv($t, 2), $t * 5, $t + max(1, intdiv($t, 4)), $t * 3, max(1, $t - max(1, intdiv($t, 5))), $t * 10] as $x) if ($x > 0 && $x !== $t) $c[$x] = 1;
        return array_map(fn($x) => self::tri((string)$x), self::sub(array_keys($c), 3, $r));
    }

    /* ------------------------------------------------------------------ the bank */

    /** One generated question, or null (unknown service / level / N). N runs from 1 to perLevel(). */
    public static function question(string $slug, int $level, int $n): ?array
    {
        $s = self::svc($slug);
        if (!$s || !isset(self::SCHED[$level]) || $n < 1) return null;
        $sch = self::SCHED[$level]; $len = count($sch); $pos = ($n - 1) % $len; $round = intdiv($n - 1, $len);
        $kind = $sch[$pos]; $f = $round + $pos;
        $r = self::rnd($slug . '|' . $level . '|' . $n);
        $b = self::build($kind, $s, $f, $r, $round);
        if (!$b) return null;
        [$stem, $right, $wrong, $expl, $vals] = $b; $type = $b[5] ?? 'single'; $tags = $b[6] ?? [];

        // three wordings: bare, "service sheet", "catalogue check"
        $frame = $r(3);
        if ($frame === 1) $stem = ['en' => 'Per the service sheet: ' . $stem['en'], 'fr' => 'D’après la fiche du service : ' . $stem['fr'], 'ar' => 'وفق بطاقة الخدمة: ' . $stem['ar']];
        if ($frame === 2) $stem = ['en' => 'Catalogue check. ' . $stem['en'], 'fr' => 'Vérification du catalogue. ' . $stem['fr'], 'ar' => 'فحص الكتالوج. ' . $stem['ar']];

        $vals += ['svc' => $s['title'], 'macro' => $s['_macro']];
        $ch = []; $seen = [];
        foreach ([[$right, true], [$wrong, false]] as [$list, $ok]) foreach ($list as $c) { if (isset($seen[$c['en']])) continue; $seen[$c['en']] = 1; $ch[] = [$c, $ok]; }
        $ch = self::shuf($ch, $r);
        $nr = count(array_filter($ch, fn($x) => $x[1]));
        if (count($ch) < 2 || $nr < 1 || $nr === count($ch) || ($type === 'multiple' && $nr < 2) || ($type === 'single' && $nr !== 1)) return null;
        $ids = 'abcdefgh'; $choices = []; $ans = [];
        foreach ($ch as $i => [$c, $ok]) { $choices[] = ['id' => $ids[$i], 't' => $c]; if ($ok) $ans[] = $ids[$i]; }
        return ['id' => sprintf('%s.L%d.g%04d', $slug, $level, $n), 'level' => $level, 'type' => $type, 'active' => true, 'tags' => $tags, 'gen' => true, 'kind' => $kind,
            'q' => self::fill($stem, $vals), 'choices' => $choices, 'answer' => $ans, 'explain' => self::fill($expl, $vals)];
    }

    /** Same idea = same stem + same right answers (used so one round never shows the same question twice). */
    public static function concept(array $q): string
    {
        $right = [];
        foreach ($q['choices'] as $c) if (in_array($c['id'], $q['answer'], true)) $right[] = $c['t']['en'];
        sort($right);
        return $q['q']['en'] . '|' . implode('|', $right);
    }

    /** Whole rendered question (stem + all choices): used by the test to measure how many generated questions are really different. */
    public static function signature(array $q): string
    {
        $c = array_map(fn($x) => $x['t']['en'], $q['choices']);
        sort($c);
        return $q['q']['en'] . '|' . implode('|', $c);
    }

    /** @return ?array [stem, right[], wrong[], explain, vals, type?, tags?] */
    private static function build(string $kind, array $s, int $f, \Closure $r, int $round): ?array
    {
        $T = [self::class, 'tri'];
        $D = $s['deliverables']; $R = $s['_risks'];
        $d = $D[$f % 4]; $rk = $R[$f % count($R)];
        $dv = ['d' => $d['name'], 'u' => $d['unit'], 't' => (string)$d['target']];
        $svcOf = fn(string $slug) => Content::get('services', $slug)['title'] ?? self::tri($slug);
        $own = fn(array $list) => $list;

        switch ($kind) {
            // ================================ level 1
            case 'macro':
                $others = [];
                foreach (Content::all('macros') as $slug => $m) if ($slug !== ($s['macro'] ?? '')) $others[] = $m['name'];
                return [$T('Which macro work does the service “{svc}” belong to?', 'À quelle macro-œuvre appartient le service « {svc} » ?', 'إلى أي عمل كبير تنتمي خدمة «{svc}»؟'), [$s['_macro']], self::sub($others, 3, $r),
                    $T('“{svc}” is one of the services of the macro work “{macro}”.', '« {svc} » est l’un des services de la macro-œuvre « {macro} ».', 'خدمة «{svc}» إحدى خدمات العمل الكبير «{macro}».'), [], 'single', ['catalogue']];
            case 'del':
                return [$T('Which of these is a deliverable of “{svc}”?', 'Lequel de ces éléments est un livrable de « {svc} » ?', 'أي مما يلي مخرَج من مخرجات خدمة «{svc}»؟'), [$d['name']], self::pick('del', $s, 3, array_column($D, 'name'), $r),
                    $T('“{d}” is listed among the weekly deliverables of “{svc}”.', '« {d} » figure parmi les livrables hebdomadaires de « {svc} ».', '«{d}» من المخرجات الأسبوعية لخدمة «{svc}».'), $dv, 'single', ['deliverables']];
            case 'notdel':
                return [$T('Which of these is NOT a deliverable of “{svc}”?', 'Lequel de ces éléments n’est PAS un livrable de « {svc} » ?', 'أي مما يلي ليس من مخرجات خدمة «{svc}»؟'), self::pick('del', $s, 1, array_column($D, 'name'), $r),
                    self::sub(array_column($D, 'name'), 3, $r), $T('One item belongs to another service; the three others are deliverables of “{svc}”.', 'Un élément appartient à un autre service ; les trois autres sont des livrables de « {svc} ».', 'عنصر واحد يخص خدمة أخرى؛ أما الثلاثة الأخرى فمن مخرجات «{svc}».'), [], 'single', ['deliverables']];
            case 'unit':
                return [$T('In which unit is “{d}” counted for “{svc}”?', 'En quelle unité « {d} » est-il compté pour « {svc} » ?', 'بأي وحدة تُحسب «{d}» في خدمة «{svc}»؟'), [$d['unit']], self::pick('unit', $s, 3, array_column($D, 'unit'), $r),
                    $T('“{d}” is counted in: {u}.', '« {d} » est compté en : {u}.', '«{d}» تُحسب بوحدة: {u}.'), $dv, 'single', ['deliverables']];
            case 'all':
                $k = 2 + $round % 2; $right = [];
                for ($i = 0; $i < $k; $i++) $right[] = $D[($f + $i) % 4]['name'];
                return [$T('Select all the deliverables of “{svc}”.', 'Sélectionnez tous les livrables de « {svc} ».', 'اختر كل مخرجات خدمة «{svc}».'), $right, self::pick('del', $s, 2, array_column($D, 'name'), $r),
                    $T('The deliverables of “{svc}” are listed on its service page; the other items belong to different services.', 'Les livrables de « {svc} » sont listés sur sa page de service ; les autres éléments appartiennent à d’autres services.', 'مخرجات «{svc}» مذكورة في صفحة الخدمة؛ أما العناصر الأخرى فتخص خدمات أخرى.'), [], 'multiple', ['deliverables']];
            case 'fields':
                return [$T('Which fields of study does “{svc}” draw on?', 'Sur quels domaines d’études « {svc} » s’appuie-t-il ?', 'على أي مجالات دراسية تعتمد خدمة «{svc}»؟'), [self::cap($s['fields'])], array_map([self::class, 'cap'], self::pick('fields', $s, 3, [$s['fields']], $r)),
                    $T('“{svc}” draws on: {f}.', '« {svc} » s’appuie sur : {f}.', 'تعتمد «{svc}» على: {f}.'), ['f' => $s['fields']], 'single', ['catalogue']];
            case 'region':
                return [$T('Where is “{svc}” piloted first?', 'Où « {svc} » est-il piloté en premier ?', 'أين تُجرَّب خدمة «{svc}» أولا؟'), [$s['region']], self::pick('region', $s, 3, [$s['region']], $r),
                    $T('The pilot sites of “{svc}” are: {g}.', 'Les sites pilotes de « {svc} » sont : {g}.', 'مواقع التجربة لخدمة «{svc}» هي: {g}.'), ['g' => $s['region']], 'single', ['catalogue']];
            case 'thr':
                return [$T('For a team working on “{svc}”, what is the minimum number of the 10 eligible weekly reports to submit by Friday 12:00 to meet the pilot threshold?', 'Pour une équipe travaillant sur « {svc} », quel est le nombre minimal de rapports hebdomadaires admissibles sur 10 à remettre avant vendredi 12:00 pour atteindre le seuil pilote ?', 'لفريق يعمل على «{svc}»، ما الحد الأدنى من التقارير الأسبوعية المؤهلة من أصل 10 الواجب تسليمها قبل الجمعة 12:00 لبلوغ حد التجربة؟'),
                    [$T('9')], array_map(fn($x) => $T((string)$x), self::sub([3, 4, 5, 6, 7, 8], 3, $r)),
                    $T('The pilot threshold is at least 9 of 10 eligible weekly reports, submitted by Friday 12:00.', 'Le seuil pilote est d’au moins 9 rapports hebdomadaires admissibles sur 10, remis avant vendredi 12:00.', 'حد التجربة هو 9 تقارير أسبوعية مؤهلة على الأقل من أصل 10، تُسلَّم قبل الجمعة 12:00.'), [], 'single', ['weekly']];
            case 'cert':
                return [$T('After you pass a W5D QCM round on “{svc}”, what does your certificate state?', 'Après avoir réussi un QCM W5D sur « {svc} », que déclare votre certificat ?', 'بعد نجاحك في جولة QCM لدى W5D على «{svc}»، ماذا تذكر شهادتك؟'),
                    [$T('What you demonstrated on that test, with a code anyone can verify', 'Ce que vous avez démontré lors de ce test, avec un code que chacun peut vérifier', 'ما أثبتَّه في ذلك الاختبار، مع رمز يمكن لأي شخص التحقق منه')],
                    self::sub([$T('A guaranteed job at W5D', 'Un emploi garanti chez W5D', 'وظيفة مضمونة لدى W5D'), $T('A state diploma', 'Un diplôme d’État', 'شهادة دولة رسمية'), $T('A promised investment return', 'Un rendement d’investissement promis', 'عائد استثماري موعود'), $T('A guaranteed income', 'Un revenu garanti', 'دخل مضمون'), $T('Lifetime membership', 'Une adhésion à vie', 'عضوية مدى الحياة')], 3, $r),
                    $T('A certificate records a demonstrated result on a test. It is a path, not a job, and it promises neither work nor income.', 'Un certificat consigne un résultat démontré lors d’un test. C’est un parcours, pas un emploi, et il ne promet ni travail ni revenu.', 'تسجّل الشهادة نتيجة أُثبتت في اختبار. هي مسار وليست وظيفة، ولا تعد بعمل ولا بدخل.'), [], 'single', ['certification']];
            case 'target':
                return [$T('At full, resourced capacity, what is the pilot target for “{d}” in “{svc}”?', 'À pleine capacité dotée de ressources, quelle est la cible pilote pour « {d} » dans « {svc} » ?', 'عند الطاقة الكاملة المزوّدة بالموارد، ما الهدف التجريبي لـ«{d}» في «{svc}»؟'), [$T((string)$d['target'])], self::nums((int)$d['target'], $r),
                    $T('The pilot plan lists {t} {u} for “{d}”: a staged output from commissioned cases, not a promise.', 'Le plan pilote indique {t} {u} pour « {d} » : une production par étapes issue de cas commandés, pas une promesse.', 'تذكر الخطة التجريبية {t} {u} لـ«{d}»: مخرج مرحلي من حالات مكلَّف بها وليس وعدا.'), $dv, 'single', ['deliverables']];
            case 'whosvc':
                $avoid = [$s['title']];
                return [$T('Which service lists “{d}” among its deliverables?', 'Quel service liste « {d} » parmi ses livrables ?', 'أي خدمة تذكر «{d}» ضمن مخرجاتها؟'), [$s['title']], self::pick('title', $s, 3, $avoid, $r),
                    $T('“{d}” is a deliverable of “{svc}”.', '« {d} » est un livrable de « {svc} ».', '«{d}» من مخرجات «{svc}».'), $dv, 'single', ['catalogue']];

            // ================================ level 2
            case 'r2m':
                return [$T('According to the risk register of “{svc}”, which mitigation is paired with this risk: “{c}”?', 'Selon le registre des risques de « {svc} », quelle mesure d’atténuation est associée à ce risque : « {c} » ?', 'وفق سجل مخاطر «{svc}»، أي إجراء تخفيف مقترن بهذا الخطر: «{c}»؟'), [$rk['mit']], self::pick('mit', $s, 3, array_column($R, 'mit'), $r),
                    $T('The register pairs “{c}” with: {m}', 'Le registre associe « {c} » à : {m}', 'يقرن السجل «{c}» بـ: {m}'), ['c' => $rk['cause'], 'm' => $rk['mit']], 'single', ['risk']];
            case 'm2r':
                return [$T('In “{svc}”, which risk is mitigated by: “{m}”?', 'Dans « {svc} », quel risque est atténué par : « {m} » ?', 'في «{svc}»، أي خطر يُخفَّف بـ: «{m}»؟'), [$rk['cause']], self::pick('cause', $s, 3, array_column($R, 'cause'), $r),
                    $T('That mitigation is documented for the risk “{c}”.', 'Cette mesure est documentée pour le risque « {c} ».', 'هذا الإجراء موثّق للخطر «{c}».'), ['c' => $rk['cause'], 'm' => $rk['mit']], 'single', ['risk']];
            case 'acc':
                $good = [$T('A PDF/A acceptance dossier', 'Un dossier d’acceptation PDF/A', 'ملف قبول بصيغة PDF/A'), $T('A CSV/JSON source manifest', 'Un manifeste de sources CSV/JSON', 'بيان مصادر بصيغة CSV/JSON'), $T('Links from each unit to its source, reviewer and version', 'Des liens de chaque unité vers sa source, son relecteur et sa version', 'روابط من كل وحدة إلى مصدرها ومراجعها وإصدارها')];
                $bad = [$T('A promise of future income', 'Une promesse de revenus futurs', 'وعد بدخل مستقبلي'), $T('A screenshot with no source', 'Une capture d’écran sans source', 'لقطة شاشة بلا مصدر'), $T('The investor’s order reference', 'La référence de commande de l’investisseur', 'مرجع طلب المستثمر'), $T('Today’s price of a part', 'Le prix du jour d’une part', 'سعر الحصة اليوم')];
                return [$T('Which items belong to the acceptance package of “{svc}”?', 'Quels éléments font partie du dossier d’acceptation de « {svc} » ?', 'أي العناصر تدخل في حزمة قبول «{svc}»؟'), self::sub($good, 2 + $round % 2, $r), self::sub($bad, 2, $r),
                    $T('The acceptance package combines a PDF/A dossier and a CSV/JSON source manifest, so every unit can be traced to its source. Orders and prices are not part of it.', 'Le dossier d’acceptation combine un dossier PDF/A et un manifeste de sources CSV/JSON, afin que chaque unité soit traçable jusqu’à sa source. Commandes et prix n’en font pas partie.', 'تجمع حزمة القبول ملف PDF/A وبيان مصادر CSV/JSON ليمكن تتبع كل وحدة إلى مصدرها. الطلبات والأسعار ليست جزءا منها.'), [], 'multiple', ['evidence']];
            case 'dep':
                $dep = $s['depends'][$f % count($s['depends'])]['slug'];
                return [$T('Which service does “{svc}” depend on?', 'De quel service « {svc} » dépend-il ?', 'على أي خدمة تعتمد «{svc}»؟'), [$svcOf($dep)], self::pick('title', $s, 3, array_map(fn($x) => $svcOf($x['slug']), $s['depends']), $r),
                    $T('“{svc}” depends on “{x}” for its inputs.', '« {svc} » dépend de « {x} » pour ses entrées.', 'تعتمد «{svc}» على «{x}» في مدخلاتها.'), ['x' => $svcOf($dep)], 'single', ['dependencies']];
            case 'feed':
                $fd = $s['feeds'][0]['slug'];
                return [$T('Which service does “{svc}” feed with its output?', 'Quel service « {svc} » alimente-t-il avec ses résultats ?', 'أي خدمة تغذّيها «{svc}» بمخرجاتها؟'), [$svcOf($fd)], self::pick('title', $s, 3, array_merge([$svcOf($fd)], array_map(fn($x) => $svcOf($x['slug']), $s['depends'])), $r),
                    $T('“{svc}” feeds “{x}”.', '« {svc} » alimente « {x} ».', 'تغذّي «{svc}» خدمة «{x}».'), ['x' => $svcOf($fd)], 'single', ['dependencies']];
            case 'sdg':
            case 'sdgt':
                $g = $s['sdg'][$f % count($s['sdg'])]; $ownG = array_column($s['sdg'], 'goal');
                if ($kind === 'sdg') {
                    $cand = []; for ($x = 1; $x <= 17; $x++) if (!in_array((string)$x, $ownG, true)) $cand[] = $x;
                    return [$T('Which Sustainable Development Goal (SDG) is linked to “{svc}”?', 'Quel Objectif de développement durable (ODD) est lié à « {svc} » ?', 'أي هدف من أهداف التنمية المستدامة مرتبط بخدمة «{svc}»؟'), [$T('SDG ' . $g['goal'], 'ODD ' . $g['goal'], 'الهدف ' . $g['goal'])],
                        array_map(fn($x) => $T('SDG ' . $x, 'ODD ' . $x, 'الهدف ' . $x), self::sub($cand, 3, $r)),
                        $T('“{svc}” is mapped to SDG {g}.', '« {svc} » est rattaché à l’ODD {g}.', 'ترتبط «{svc}» بالهدف {g}.'), ['g' => $g['goal']], 'single', ['sdg']];
                }
                $ownT = array_column($s['sdg'], 'target'); $cand = [];
                for ($x = 1; $x <= 17; $x++) for ($y = 1; $y <= 9; $y++) if (!in_array("$x.$y", $ownT, true)) $cand[] = "$x.$y";
                return [$T('Which SDG target is mapped to “{svc}”?', 'Quelle cible des ODD est rattachée à « {svc} » ?', 'أي غاية من غايات أهداف التنمية المستدامة مرتبطة بخدمة «{svc}»؟'), [$T('Target ' . $g['target'], 'Cible ' . $g['target'], 'الغاية ' . $g['target'])],
                    array_map(fn($x) => $T('Target ' . $x, 'Cible ' . $x, 'الغاية ' . $x), self::sub($cand, 3, $r)),
                    $T('“{svc}” is mapped to SDG target {g}.', '« {svc} » est rattaché à la cible {g} des ODD.', 'ترتبط «{svc}» بالغاية {g}.'), ['g' => $g['target']], 'single', ['sdg']];
            case 'weekly':
                $good = [$T('At least 9 of 10 eligible reports are submitted by Friday 12:00', 'Au moins 9 rapports admissibles sur 10 sont remis avant vendredi 12:00', '9 تقارير مؤهلة على الأقل من أصل 10 تُسلَّم قبل الجمعة 12:00'), $T('No more than 1 evidence-related rework request per team-week', 'Pas plus d’une demande de reprise liée aux preuves par équipe et par semaine', 'لا أكثر من طلب إعادة عمل واحد متعلق بالأدلة لكل فريق في الأسبوع')];
                $bad = [$T('Late reports count in full if sent before month end', 'Les rapports tardifs comptent en entier s’ils sont envoyés avant la fin du mois', 'تُحتسب التقارير المتأخرة كاملة إن أُرسلت قبل نهاية الشهر'), $T('Reports are optional for experienced members', 'Les rapports sont facultatifs pour les membres expérimentés', 'التقارير اختيارية للأعضاء ذوي الخبرة'), $T('The threshold is lowered when the team is busy', 'Le seuil est abaissé quand l’équipe est occupée', 'يُخفَّض الحد حين يكون الفريق مشغولا')];
                return [$T('Which of these are weekly-report rules for “{svc}”?', 'Lesquelles de ces règles s’appliquent aux rapports hebdomadaires de « {svc} » ?', 'أي مما يلي من قواعد التقارير الأسبوعية لخدمة «{svc}»؟'), $good, self::sub($bad, 1 + $round % 2, $r),
                    $T('The pilot rules are: at least 9 of 10 eligible reports by Friday 12:00, and at most 1 evidence-related rework request per team-week.', 'Les règles pilotes : au moins 9 rapports admissibles sur 10 avant vendredi 12:00, et au plus 1 demande de reprise liée aux preuves par équipe-semaine.', 'قواعد التجربة: 9 تقارير مؤهلة على الأقل من 10 قبل الجمعة 12:00، وطلب إعادة عمل واحد على الأكثر متعلق بالأدلة لكل فريق-أسبوع.'), [], 'multiple', ['weekly']];
            case 'leave':
                return [$T('A team member working on “{svc}” is on approved leave this week. What changes in the weekly check?', 'Un membre d’une équipe travaillant sur « {svc} » est en congé approuvé cette semaine. Qu’est-ce qui change dans le contrôle hebdomadaire ?', 'عضو في فريق يعمل على «{svc}» في إجازة معتمدة هذا الأسبوع. ما الذي يتغير في الفحص الأسبوعي؟'), [$T('The eligible denominator changes', 'Le dénominateur admissible change', 'يتغير المقام المؤهل')],
                    self::sub([$T('The 9-of-10 threshold is waived for good', 'Le seuil de 9 sur 10 est supprimé définitivement', 'يُلغى حد 9 من 10 نهائيا'), $T('Their reports are marked late', 'Leurs rapports sont marqués en retard', 'تُوسم تقاريرهم بالتأخر'), $T('The whole team is exempt this week', 'Toute l’équipe est dispensée cette semaine', 'يُعفى الفريق كله هذا الأسبوع'), $T('The sample rate is doubled', 'Le taux d’échantillonnage est doublé', 'يُضاعف معدل العينة')], 3, $r),
                    $T('Approved leave and adjustments change the eligible denominator; the threshold itself stays.', 'Les congés et aménagements approuvés modifient le dénominateur admissible ; le seuil lui-même reste.', 'الإجازات والتعديلات المعتمدة تغيّر المقام المؤهل؛ أما الحد نفسه فيبقى.'), [], 'single', ['weekly']];
            case 'place':
                $all = ['Real 1.0', 'Between 1.0', 'Real 0.5 / Virtual 0.5']; $own = (string)($s['placement'] ?? '');
                if (!in_array($own, $all, true)) return null;
                return [$T('Which placement mode is listed for “{svc}” in the catalogue?', 'Quel mode de placement est indiqué pour « {svc} » dans le catalogue ?', 'أي نمط تعيين مذكور لخدمة «{svc}» في الكتالوج؟'), [$T($own)], array_map(fn($x) => $T($x), array_values(array_diff($all, [$own]))),
                    $T('The catalogue lists the placement of “{svc}” as: ' . $own . '.', 'Le catalogue indique le placement de « {svc} » comme : ' . $own . '.', 'يذكر الكتالوج نمط تعيين «{svc}» بأنه: ' . $own . '.'), [], 'single', ['catalogue']];

            // ================================ level 3
            case 'bound':
                return [$T('Which statement is the governing boundary of “{svc}”?', 'Quel énoncé est la limite directrice de « {svc} » ?', 'أي عبارة هي الحد الحاكم لخدمة «{svc}»؟'), [self::cap($s['boundary'])], self::pick('bound', $s, 3, [self::cap($s['boundary'])], $r),
                    $T('The governing boundary of “{svc}”: {b}.', 'La limite directrice de « {svc} » : {b}.', 'الحد الحاكم لخدمة «{svc}»: {b}.'), ['b' => $s['boundary']], 'single', ['boundary']];
            case 'case':
                $same = []; foreach ($R as $x) if ($x['mit']['en'] !== $rk['mit']['en']) $same[] = $x['mit'];
                return [$T('In a case of “{svc}”, this happens: “{c}”. Which documented response applies?', 'Dans un dossier de « {svc} », ceci se produit : « {c} ». Quelle réponse documentée s’applique ?', 'في حالة من «{svc}» يحدث ما يلي: «{c}». أي استجابة موثّقة تنطبق؟'), [$rk['mit']],
                    array_merge(self::sub($same, 2, $r), [$T('Carry on and document it later', 'Continuer et le documenter plus tard', 'المتابعة وتوثيق الأمر لاحقا')]),
                    $T('The documented response is: {m}', 'La réponse documentée est : {m}', 'الاستجابة الموثّقة هي: {m}'), ['c' => $rk['cause'], 'm' => $rk['mit']], 'single', ['risk', 'case']];
            case 'incomplete':
                return [$T('An acceptance dossier for “{svc}” arrives as a PDF/A file but without the CSV/JSON source manifest. What is the correct status?', 'Un dossier d’acceptation de « {svc} » arrive en PDF/A mais sans le manifeste de sources CSV/JSON. Quel est le statut correct ?', 'يصل ملف قبول لخدمة «{svc}» بصيغة PDF/A دون بيان المصادر CSV/JSON. ما الحالة الصحيحة؟'),
                    [$T('Incomplete: ask for the manifest before accepting', 'Incomplet : demander le manifeste avant d’accepter', 'غير مكتمل: اطلب البيان قبل القبول')],
                    self::sub([$T('Accepted: the PDF/A is enough', 'Accepté : le PDF/A suffit', 'مقبول: ملف PDF/A يكفي'), $T('Accepted with a price discount', 'Accepté avec une remise', 'مقبول مع خصم في السعر'), $T('Rejected for good, with no chance to complete it', 'Rejeté définitivement, sans possibilité de le compléter', 'مرفوض نهائيا دون فرصة لاستكماله'), $T('Accepted if the leader vouches for it', 'Accepté si le chef d’équipe s’en porte garant', 'مقبول إن تعهد القائد به')], 3, $r),
                    $T('The package needs both parts; a missing manifest makes it incomplete, not rejected.', 'Le dossier exige les deux parties ; un manifeste manquant le rend incomplet, pas rejeté.', 'تتطلب الحزمة الجزأين؛ غياب البيان يجعلها غير مكتملة لا مرفوضة.'), [], 'single', ['evidence']];
            case 'links':
                return [$T('The acceptance package links “{d}” to which of these?', 'Le dossier d’acceptation relie « {d} » à quels éléments ?', 'بأي من هذه العناصر تربط حزمة القبول «{d}»؟'),
                    self::sub([$T('The source', 'La source', 'المصدر'), $T('The reviewer', 'Le relecteur', 'المراجع'), $T('The exception log', 'Le journal des exceptions', 'سجل الاستثناءات'), $T('The version', 'La version', 'الإصدار')], 2 + $round % 2, $r),
                    self::sub([$T('The investor’s order reference', 'La référence de commande de l’investisseur', 'مرجع طلب المستثمر'), $T('Today’s price of a part', 'Le prix du jour d’une part', 'سعر الحصة اليوم'), $T('A member’s pay', 'La rémunération d’un membre', 'أجر عضو')], 2, $r),
                    $T('The package links each unit to its source, reviewer, version, exception log and authorisation. Orders and prices are not part of it.', 'Le dossier relie chaque unité à sa source, son relecteur, sa version, son journal d’exceptions et son autorisation. Commandes et prix n’en font pas partie.', 'تربط الحزمة كل وحدة بمصدرها ومراجعها وإصدارها وسجل استثناءاتها وإذنها. الطلبات والأسعار ليست جزءا منها.'), $dv, 'multiple', ['evidence']];
            case 'rework':
                $k = 2 + $round % 5;
                return [$T('A team working on “{svc}” has {k} evidence-related rework requests in one week. Against the pilot limit of at most 1, this is…', 'Une équipe de « {svc} » a {k} demandes de reprise liées aux preuves en une semaine. Par rapport à la limite pilote d’au plus 1, c’est…', 'فريق في «{svc}» لديه {k} طلبات إعادة عمل متعلقة بالأدلة في أسبوع. مقابل حد التجربة (1 على الأكثر) هذا…'),
                    [$T('Above the limit: a corrective action is needed', 'Au-dessus de la limite : une action corrective est nécessaire', 'فوق الحد: يلزم إجراء تصحيحي')],
                    self::sub([$T('Within the limit', 'Dans la limite', 'ضمن الحد'), $T('Irrelevant if the reports were on time', 'Sans importance si les rapports étaient à l’heure', 'غير مهم إن كانت التقارير في موعدها'), $T('Counted only at month end', 'Compté seulement en fin de mois', 'يُحتسب في نهاية الشهر فقط')], 3, $r),
                    $T('The pilot limit is at most 1 evidence-related rework request per team-week.', 'La limite pilote est d’au plus 1 demande de reprise liée aux preuves par équipe-semaine.', 'حد التجربة هو طلب إعادة عمل واحد على الأكثر متعلق بالأدلة لكل فريق-أسبوع.'), ['k' => (string)$k], 'single', ['weekly']];
            case 'crit':
                return [$T('A team on “{svc}” met every threshold, but a critical safety or privacy defect is found. The weekly score is…', 'Une équipe de « {svc} » a atteint tous les seuils, mais un défaut critique de sécurité ou de confidentialité est découvert. Le score hebdomadaire est…', 'فريق في «{svc}» بلغ كل الحدود، لكن اكتُشف عيب حرج في السلامة أو الخصوصية. النتيجة الأسبوعية…'),
                    [$T('Not green: the critical defect overrides it', 'Pas vert : le défaut critique prévaut', 'ليست خضراء: العيب الحرج يتجاوزها')],
                    self::sub([$T('Green: thresholds decide alone', 'Vert : les seuils décident seuls', 'خضراء: الحدود وحدها تقرر'), $T('Green, with a note', 'Vert, avec une note', 'خضراء مع ملاحظة'), $T('Green if the client does not complain', 'Vert si le client ne se plaint pas', 'خضراء إن لم يشتكِ العميل')], 3, $r),
                    $T('Critical safety or privacy defects override a green score.', 'Les défauts critiques de sécurité ou de confidentialité prévalent sur un score vert.', 'العيوب الحرجة في السلامة أو الخصوصية تتجاوز النتيجة الخضراء.'), [], 'single', ['weekly']];
            case 'tread':
                return [$T('“{svc}” lists a full-capacity pilot target of {t} for “{d}”. How should that figure be read?', '« {svc} » indique pour « {d} » une cible pilote à pleine capacité de {t}. Comment lire ce chiffre ?', 'تذكر «{svc}» هدفا تجريبيا بكامل الطاقة قدره {t} لـ«{d}». كيف يُقرأ هذا الرقم؟'),
                    [$T('A staged output from commissioned cases, not a promise', 'Une production par étapes issue de cas commandés, pas une promesse', 'مخرج مرحلي من حالات مكلَّف بها، وليس وعدا')],
                    self::sub([$T('A guarantee delivered every week', 'Une garantie livrée chaque semaine', 'ضمان يُسلَّم كل أسبوع'), $T('A minimum paid to each member', 'Un minimum versé à chaque membre', 'حد أدنى يُدفع لكل عضو'), $T('A promise of income for investors', 'Une promesse de revenu pour les investisseurs', 'وعد بدخل للمستثمرين'), $T('A penalty charged if it is missed', 'Une pénalité facturée en cas de manquement', 'غرامة تُفرض عند عدم بلوغه')], 3, $r),
                    $T('Targets are pilot outputs at full, resourced capacity, from commissioned cases. They are not a promise to finish a whole programme each week.', 'Les cibles sont des productions pilotes à pleine capacité dotée de ressources, issues de cas commandés. Ce n’est pas la promesse d’achever un programme entier chaque semaine.', 'الأهداف مخرجات تجريبية بكامل الطاقة المزوّدة بالموارد من حالات مكلَّف بها، وليست وعدا بإنجاز برنامج كامل كل أسبوع.'), $dv, 'single', ['deliverables']];
            case 'unitrev':
                $others = []; foreach ($D as $x) if ($x['name']['en'] !== $d['name']['en']) $others[] = $x['name'];
                return [$T('Which deliverable of “{svc}” is counted in “{u}”?', 'Quel livrable de « {svc} » est compté en « {u} » ?', 'أي مخرَج من مخرجات «{svc}» يُحسب بوحدة «{u}»؟'), [$d['name']], $others,
                    $T('“{d}” is the deliverable counted in “{u}”.', '« {d} » est le livrable compté en « {u} ».', '«{d}» هو المخرَج الذي يُحسب بوحدة «{u}».'), $dv, 'single', ['deliverables']];
            case 'kpi':
                if (!preg_match('/≥\s*(\d+)\s+accepted\s+(.+?)(?:\*\*|\s+per\s+team-week)/u', (string)($s['kpi']['en'] ?? ''), $m)) return null;
                $nmin = (int)$m[1]; $ui = 0;
                foreach ($D as $i => $x) if (strcasecmp(trim($x['unit']['en']), trim($m[2])) === 0) $ui = $i;
                $u = $D[$ui]['unit']; $met = $round % 2 === 0 || $nmin < 2; $got = $met ? $nmin : $nmin - 1;
                return [$T('The pilot proposes at least {n} accepted {u} per team-week for “{svc}”. A team delivers {got}. What is the status of that threshold?', 'Le pilote propose au moins {n} {u} acceptés par équipe-semaine pour « {svc} ». Une équipe en livre {got}. Quel est le statut de ce seuil ?', 'تقترح التجربة {n} من «{u}» المقبولة على الأقل لكل فريق-أسبوع في «{svc}». سلّم فريق {got}. ما حالة هذا الحد؟'),
                    [$met ? $T('Met', 'Atteint', 'مُحقَّق') : $T('Not met', 'Non atteint', 'غير مُحقَّق')],
                    [$met ? $T('Not met', 'Non atteint', 'غير مُحقَّق') : $T('Met', 'Atteint', 'مُحقَّق'), $T('Waived', 'Dispensé', 'مُعفى'), $T('Suspended', 'Suspendu', 'مُعلَّق')],
                    $T('The proposed threshold is at least {n}; {got} ' . ($met ? 'meets' : 'is below') . ' it (subject to case complexity).', 'Le seuil proposé est d’au moins {n} ; {got} ' . ($met ? 'l’atteint' : 'est en dessous') . ' (selon la complexité du cas).', 'الحد المقترح هو {n} على الأقل؛ والعدد {got} ' . ($met ? 'يحققه' : 'دونه') . ' (رهنا بتعقيد الحالة).'), ['n' => (string)$nmin, 'got' => (string)$got, 'u' => $u], 'single', ['weekly']];
            case 'nosource':
                return [$T('A unit of “{d}” for “{svc}” arrives without a reference to its source. What is the correct handling?', 'Une unité de « {d} » pour « {svc} » arrive sans référence à sa source. Quelle est la bonne conduite ?', 'تصل وحدة من «{d}» لخدمة «{svc}» دون إشارة إلى مصدرها. ما التصرف الصحيح؟'),
                    [$T('Return it as incomplete: every unit must link to its source', 'La renvoyer comme incomplète : chaque unité doit renvoyer à sa source', 'إعادتها كغير مكتملة: يجب أن ترتبط كل وحدة بمصدرها')],
                    self::sub([$T('Accept it if the reviewer signs anyway', 'L’accepter si le relecteur signe quand même', 'قبولها إن وقّع المراجع على أي حال'), $T('Accept it with a price discount', 'L’accepter avec une remise', 'قبولها مع خصم في السعر'), $T('Delete it without telling anyone', 'La supprimer sans prévenir personne', 'حذفها دون إبلاغ أحد'), $T('Count it and fix the source later', 'La compter et corriger la source plus tard', 'احتسابها وتصحيح المصدر لاحقا')], 3, $r),
                    $T('The acceptance package links every unit to its source; a unit without one is incomplete and goes back.', 'Le dossier d’acceptation relie chaque unité à sa source ; une unité sans source est incomplète et repart.', 'تربط حزمة القبول كل وحدة بمصدرها؛ والوحدة بلا مصدر غير مكتملة وتُعاد.'), $dv, 'single', ['evidence', 'case']];

            // ================================ level 4
            case 'samp':
                return [$T('What share of the weekly work does the integrity layer sample for “{svc}”?', 'Quelle part du travail hebdomadaire la couche d’intégrité échantillonne-t-elle pour « {svc} » ?', 'ما نسبة العمل الأسبوعي التي تأخذ منها طبقة النزاهة عينة في «{svc}»؟'),
                    [$T('{{ quality.sampling_percent|pct }}, drawn at random', '{{ quality.sampling_percent|pct }}, tirés au hasard', '{{ quality.sampling_percent|pct }} تُسحب عشوائيا')],
                    self::sub([$T('25%, chosen by the leader', '25 %, choisis par le chef d’équipe', '25% يختارها القائد'), $T('50%, for new members only', '50 %, pour les nouveaux membres seulement', '50% للأعضاء الجدد فقط'), $T('100%, every week', '100 %, chaque semaine', '100% كل أسبوع'), $T('1%, when the team is trusted', '1 %, quand l’équipe est de confiance', '1% حين يكون الفريق موثوقا'), $T('Whatever the client asks', 'Ce que le client demande', 'ما يطلبه العميل')], 3, $r),
                    $T('The sample rate is fixed by doctrine and random. Lowering it would dilute the integrity layer.', 'Le taux d’échantillonnage est fixé par doctrine et aléatoire. Le baisser diluerait la couche d’intégrité.', 'معدل العينة ثابت بالمبدأ وعشوائي. خفضه يُضعف طبقة النزاهة.'), [], 'single', ['integrity']];
            case 'noperson':
                return [$T('A leader on “{svc}” distrusts one member and wants a higher sample rate for that person only. What is right?', 'Un chef d’équipe de « {svc} » se méfie d’un membre et veut un taux d’échantillonnage plus élevé pour lui seul. Que faire ?', 'قائد في «{svc}» لا يثق بأحد الأعضاء ويريد معدل عينة أعلى له وحده. ما الصواب؟'),
                    [$T('Refuse: the sample is fixed and random, not tuned per person', 'Refuser : l’échantillon est fixe et aléatoire, pas réglé par personne', 'الرفض: العينة ثابتة وعشوائية وليست مضبوطة لكل شخص')],
                    self::sub([$T('Accept: the leader knows best', 'Accepter : le chef sait mieux', 'القبول: القائد أعلم'), $T('Accept for one week only', 'Accepter pour une semaine seulement', 'القبول لأسبوع واحد فقط'), $T('Accept if the member agrees', 'Accepter si le membre est d’accord', 'القبول إن وافق العضو'), $T('Accept if the client asks', 'Accepter si le client le demande', 'القبول إن طلب العميل')], 3, $r),
                    $T('The sample rate is not tuned per person. A concern is raised through the exception log and review, not by changing the sample.', 'Le taux n’est pas réglé par personne. Un doute se signale via le journal des exceptions et la relecture, pas en changeant l’échantillon.', 'لا يُضبط معدل العينة لكل شخص. تُرفع المخاوف عبر سجل الاستثناءات والمراجعة لا بتغيير العينة.'), [], 'single', ['integrity']];
            case 'corr':
                return [$T('In how many days does W5D publish a correction after an error is reported on “{svc}”?', 'En combien de jours W5D publie-t-elle une correction après le signalement d’une erreur sur « {svc} » ?', 'في كم يوما تنشر W5D تصويبا بعد الإبلاغ عن خطأ في «{svc}»؟'),
                    [$T('{{ quality.correction_sla_days }} days', '{{ quality.correction_sla_days }} jours', '{{ quality.correction_sla_days }} يوما')],
                    self::sub([$T('3 days', '3 jours', '3 أيام'), $T('45 days', '45 jours', '45 يوما'), $T('90 days', '90 jours', '90 يوما'), $T('There is no deadline', 'Il n’y a pas de délai', 'لا يوجد أجل')], 3, $r),
                    $T('Corrections are published within the declared delay.', 'Les corrections sont publiées dans le délai déclaré.', 'تُنشر التصويبات خلال الأجل المعلن.'), [], 'single', ['integrity']];
            case 'income':
                return [$T('A client of “{svc}” asks whether a W5D part guarantees income. What is the honest answer?', 'Un client de « {svc} » demande si une part W5D garantit un revenu. Quelle est la réponse honnête ?', 'يسأل عميل لـ«{svc}» هل تضمن حصة W5D دخلا. ما الجواب الأمين؟'),
                    [$T('No: it is an investment in a certified path, not a job, and no return is promised', 'Non : c’est un investissement dans un parcours certifié, pas un emploi, et aucun rendement n’est promis', 'لا: هي استثمار في مسار معتمد وليست وظيفة، ولا يُعد بأي عائد')],
                    self::sub([$T('Yes, a minimum monthly income', 'Oui, un revenu mensuel minimum', 'نعم، دخل شهري أدنى'), $T('Yes, after the first certificate', 'Oui, après le premier certificat', 'نعم، بعد أول شهادة'), $T('Yes, if the member stays a year', 'Oui, si le membre reste un an', 'نعم، إن بقي العضو سنة'), $T('Yes, a fixed yearly return', 'Oui, un rendement annuel fixe', 'نعم، عائد سنوي ثابت')], 3, $r),
                    $T('W5D never promises income: a part is an investment in a path, not a job.', 'W5D ne promet jamais de revenu : une part est un investissement dans un parcours, pas un emploi.', 'W5D لا تعد بدخل أبدا: الحصة استثمار في مسار لا وظيفة.'), [], 'single', ['integrity']];
            case 'refuse':
                $good = [$T('Sign off work that has no evidence', 'Valider un travail sans preuves', 'اعتماد عمل بلا أدلة'), $T('Change the sample rate for one person', 'Modifier le taux d’échantillonnage pour une seule personne', 'تغيير معدل العينة لشخص واحد'), $T('Hide a critical defect to keep a score green', 'Cacher un défaut critique pour garder un score vert', 'إخفاء عيب حرج للإبقاء على نتيجة خضراء')];
                $bad = [$T('Request rework when evidence is missing', 'Demander une reprise quand les preuves manquent', 'طلب إعادة العمل عند غياب الأدلة'), $T('Publish a correction within the delay', 'Publier une correction dans le délai', 'نشر تصويب خلال الأجل'), $T('Log an exception', 'Consigner une exception', 'تسجيل استثناء')];
                return [$T('Which of these must a lead expert on “{svc}” refuse to do?', 'Lesquelles de ces actions un expert référent de « {svc} » doit-il refuser ?', 'أي مما يلي يجب أن يرفضه الخبير الرائد في «{svc}»؟'), self::sub($good, 2 + $round % 2, $r), self::sub($bad, 2, $r),
                    $T('Sign-off needs evidence, the sample is not tuned per person and critical defects are never hidden. Asking for rework, publishing corrections and logging exceptions are part of the job.', 'La validation exige des preuves, l’échantillon n’est pas réglé par personne et les défauts critiques ne sont jamais cachés. Demander une reprise, publier des corrections et consigner des exceptions font partie du travail.', 'يتطلب الاعتماد أدلة، ولا تُضبط العينة لكل شخص، ولا تُخفى العيوب الحرجة. طلب إعادة العمل ونشر التصويبات وتسجيل الاستثناءات من صميم العمل.'), [], 'multiple', ['integrity']];
            case 'm2rc':
                $same = []; foreach ($R as $x) if ($x['cause']['en'] !== $rk['cause']['en']) $same[] = $x['cause'];
                return [$T('As lead expert on “{svc}”, you apply this mitigation: “{m}”. Which risk is it answering?', 'En tant qu’expert référent de « {svc} », vous appliquez cette mesure : « {m} ». À quel risque répond-elle ?', 'بصفتك الخبير الرائد في «{svc}»، تطبّق هذا الإجراء: «{m}». على أي خطر يرد؟'), [$rk['cause']],
                    array_merge($same, self::pick('cause', $s, 1, array_merge([$rk['cause']], $same), $r)),
                    $T('That mitigation answers the risk “{c}”.', 'Cette mesure répond au risque « {c} ».', 'هذا الإجراء يرد على الخطر «{c}».'), ['c' => $rk['cause'], 'm' => $rk['mit']], 'single', ['risk', 'case']];
            case 'breach':
                return [$T('Which action would breach the boundary of “{svc}” (“{b}”)?', 'Quelle action violerait la limite de « {svc} » (« {b} ») ?', 'أي إجراء يخرق حد «{svc}» («{b}»)؟'), [$T('Signing off a deliverable while ignoring that boundary', 'Valider un livrable en ignorant cette limite', 'اعتماد مخرَج مع تجاهل هذا الحد')],
                    self::sub([$T('Citing the boundary in the dossier', 'Citer la limite dans le dossier', 'ذكر الحد في الملف'), $T('Escalating a doubt about the boundary', 'Remonter un doute sur la limite', 'رفع شك بخصوص الحد'), $T('Logging an exception against the boundary', 'Consigner une exception à la limite', 'تسجيل استثناء على الحد'), $T('Asking for rework when the boundary is not met', 'Demander une reprise quand la limite n’est pas respectée', 'طلب إعادة العمل حين لا يُحترم الحد')], 3, $r),
                    $T('A boundary is a condition of acceptance: ignoring it breaches it. Citing, escalating and logging exceptions respect it.', 'Une limite est une condition d’acceptation : l’ignorer la viole. La citer, la remonter et consigner les exceptions la respecte.', 'الحد شرط من شروط القبول: تجاهله خرق له. أما ذكره ورفعه وتسجيل الاستثناءات فاحترام له.'), ['b' => $s['boundary']], 'single', ['boundary']];
            case 'true':
                $good = [$T('Approved leave changes the eligible denominator', 'Les congés approuvés modifient le dénominateur admissible', 'الإجازات المعتمدة تغيّر المقام المؤهل'), $T('A critical defect overrides a green score', 'Un défaut critique prévaut sur un score vert', 'العيب الحرج يتجاوز النتيجة الخضراء'), $T('They are pilot targets, not guarantees', 'Ce sont des cibles pilotes, pas des garanties', 'هي أهداف تجريبية وليست ضمانات')];
                $bad = [$T('Meeting them guarantees the client accepts the work', 'Les atteindre garantit que le client accepte le travail', 'بلوغها يضمن قبول العميل للعمل'), $T('They are paid out as a bonus to each member', 'Elles sont versées en prime à chaque membre', 'تُصرف مكافأة لكل عضو'), $T('They never change', 'Elles ne changent jamais', 'لا تتغير أبدا')];
                return [$T('Which statements about the weekly thresholds of “{svc}” are true?', 'Quelles affirmations sur les seuils hebdomadaires de « {svc} » sont vraies ?', 'أي العبارات عن الحدود الأسبوعية لخدمة «{svc}» صحيحة؟'), self::sub($good, 2 + $round % 2, $r), self::sub($bad, 1 + $round % 2, $r),
                    $T('The thresholds are pilot targets: leave changes the denominator and critical defects override a green score. Meeting them does not decide the client’s acceptance.', 'Les seuils sont des cibles pilotes : les congés modifient le dénominateur et les défauts critiques prévalent sur un score vert. Les atteindre ne décide pas de l’acceptation du client.', 'الحدود أهداف تجريبية: الإجازات تغيّر المقام والعيوب الحرجة تتجاوز النتيجة الخضراء. وبلوغها لا يحسم قبول العميل.'), [], 'multiple', ['weekly']];
            case 'pressure':
                return [$T('To hit the pilot target for “{d}” in “{svc}”, a team proposes to count units that lack evidence. As lead expert, you…', 'Pour atteindre la cible pilote de « {d} » dans « {svc} », une équipe propose de compter des unités sans preuves. En tant qu’expert référent, vous…', 'لبلوغ الهدف التجريبي لـ«{d}» في «{svc}»، يقترح فريق احتساب وحدات بلا أدلة. بصفتك الخبير الرائد…'),
                    [$T('Refuse: sign-off needs evidence, whatever the target', 'Refusez : la validation exige des preuves, quelle que soit la cible', 'ترفض: الاعتماد يتطلب أدلة مهما كان الهدف')],
                    self::sub([$T('Accept: the target comes first', 'Acceptez : la cible passe d’abord', 'تقبل: الهدف أولا'), $T('Accept once and document it later', 'Acceptez une fois et documentez plus tard', 'تقبل مرة وتوثق لاحقا'), $T('Accept if the client is in a hurry', 'Acceptez si le client est pressé', 'تقبل إن كان العميل مستعجلا'), $T('Accept if half the units have evidence', 'Acceptez si la moitié des unités ont des preuves', 'تقبل إن كان لنصف الوحدات أدلة')], 3, $r),
                    $T('Sign-off needs evidence for every unit. A target never justifies counting work without it.', 'La validation exige des preuves pour chaque unité. Une cible ne justifie jamais de compter un travail qui en est dépourvu.', 'يتطلب الاعتماد أدلة لكل وحدة. ولا يبرر أي هدف احتساب عمل بلا أدلة.'), $dv, 'single', ['integrity', 'case']];
            case 'notrisk':
                return [$T('Which of these is NOT in the documented risk register of “{svc}”?', 'Lequel de ces risques ne figure PAS dans le registre des risques documenté de « {svc} » ?', 'أي من هذه المخاطر ليس في سجل المخاطر الموثّق لخدمة «{svc}»؟'), self::pick('cause', $s, 1, array_column($R, 'cause'), $r), array_column($R, 'cause'),
                    $T('One risk belongs to another service; the others are in the register of “{svc}”.', 'Un risque appartient à un autre service ; les autres figurent dans le registre de « {svc} ».', 'خطر واحد يخص خدمة أخرى؛ أما البقية ففي سجل «{svc}».'), [], 'single', ['risk']];
        }
        return null;
    }
}
