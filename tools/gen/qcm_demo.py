#!/usr/bin/env python3
"""Generate the DEMO QCM bank: 10 questions per service per level (1-4) = 5,200 questions, en/fr/ar.

    python3 tools/gen/qcm_demo.py            # writes data/qcm/{service-slug}.json (all 130 services)
    python3 tools/gen/qcm_demo.py --check    # validate only, write nothing

Questions are built from each service's own data (data/content/services/*.json, data/content/macros/*.json):
deliverables, units, risks and their mitigations, evidence package, weekly-report thresholds, boundary, dependencies, SDGs.
Wrong choices come from OTHER services (seeded, so a re-run gives the same bank).
This is a DEMONSTRATION bank. The production bank (1,000 questions per service) is written by subject experts and an LLM
with the prompt in docs/qcm-generation-prompt.md, then loaded through the portal (QCM bank) or by replacing the files.
Numbers that the owner can change (sampling %, correction delay) are written as {{ tokens }} and resolved when a round starts.
"""
import glob, hashlib, json, os, random, re, sys

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
LANGS = ('en', 'fr', 'ar')
S = [json.load(open(f, encoding='utf-8')) for f in sorted(glob.glob(f'{ROOT}/data/content/services/*.json'))]
M = {os.path.basename(f)[:-5]: json.load(open(f, encoding='utf-8')) for f in glob.glob(f'{ROOT}/data/content/macros/*.json')}
BY = {s['slug']: s for s in S}


def cap(x, l):
    return x[:1].upper() + x[1:] if l != 'ar' and x else x


def split_risk(r):
    out = {}
    for l in LANGS:
        m = re.split(r'\s*[→←]\s*', r[l], maxsplit=1)
        if len(m) != 2 or not m[0].strip() or not m[1].strip():
            return None
        out[l] = m
    return {'cause': {l: out[l][0].strip().rstrip('.') for l in LANGS}, 'mit': {l: out[l][1].strip() for l in LANGS}}


for s in S:
    s['_risks'] = [x for x in (split_risk(r) for r in s['risks']) if x]
    if not s['_risks']:
        raise SystemExit('no usable risk in ' + s['slug'])

TXT = lambda v: {l: v[l] for l in LANGS}


def seeded(*a):
    return random.Random(int(hashlib.sha256('|'.join(map(str, a)).encode()).hexdigest()[:12], 16))


class Pool:
    """Candidate wrong answers from other services; never the same English text as an avoided one."""
    def __init__(self, fn):
        self.items = []
        for s in S:
            for v in fn(s):
                self.items.append((s['slug'], s['macro'], v))

    def pick(self, svc, n, avoid, rng, same_macro=1):
        seen = {a['en'] for a in avoid}
        c = [(m, v) for (o, m, v) in self.items if o != svc['slug'] and v['en'] not in seen]
        rng.shuffle(c)
        pref = [v for (m, v) in c if m == svc['macro']][:same_macro] if same_macro else []
        out = list(pref)
        for (m, v) in c:
            if len(out) >= n:
                break
            if v['en'] not in {x['en'] for x in out}:
                out.append(v)
        return out[:n]


P_NAMES = Pool(lambda s: [d['name'] for d in s['deliverables']])
P_UNITS = Pool(lambda s: [d['unit'] for d in s['deliverables']])
P_FIELDS = Pool(lambda s: [s['fields']])
P_REGION = Pool(lambda s: [s['region']])
P_BOUND = Pool(lambda s: [s['boundary']])
P_TITLES = Pool(lambda s: [s['title']])
P_CAUSE = Pool(lambda s: [r['cause'] for r in s['_risks']])
P_MIT = Pool(lambda s: [r['mit'] for r in s['_risks']])


class _Keep(dict):
    def __missing__(self, k):
        return '{' + k + '}'


def fill(tpl, vals, strict=True):
    out = {}
    for l in LANGS:
        v = {k: (x[l] if isinstance(x, dict) else x) for k, x in vals.items()}
        out[l] = tpl[l].format(**v) if strict else tpl[l].format_map(_Keep(v))
    return out


class Q:
    def __init__(self, svc, level, n):
        self.s, self.level, self.n = svc, level, n
        self.rng = seeded(svc['slug'], level, n)


def make(svc, level, n, tpl, vals, right, wrong, explain, kind='single', tags=()):
    rng = seeded(svc['slug'], level, n)
    ch = [(c, True) for c in right] + [(c, False) for c in wrong]
    seen = set(); uniq = []
    for c, ok in ch:
        if c['en'] in seen:
            continue
        seen.add(c['en']); uniq.append((c, ok))
    rng.shuffle(uniq)
    ids = 'abcdefgh'
    return {'id': f"{svc['slug']}.L{level}.{n:03d}", 'level': level, 'type': kind, 'active': True, 'tags': list(tags),
            'q': fill(tpl, vals), 'choices': [{'id': ids[i], 't': TXT(c)} for i, (c, _) in enumerate(uniq)],
            'answer': [ids[i] for i, (_, ok) in enumerate(uniq) if ok], 'explain': fill(explain, vals, strict=False)}


def T(en, fr, ar):
    return {'en': en, 'fr': fr, 'ar': ar}


def C(en, fr, ar):
    return {'en': en, 'fr': fr, 'ar': ar}


def gen(svc):
    qs = []
    D = svc['deliverables']; R = svc['_risks']; ttl = svc['title']
    macro = M[svc['macro']]['name']
    base = {'svc': ttl, 'macro': macro}

    def rk(i):
        return R[i % len(R)]

    def rng_(level, n):
        return seeded(svc['slug'], level, n, 'pick')

    # ============================== LEVEL 1: Foundation (recognise the service) ==============================
    L = 1; n = 0
    n += 1; r = rng_(L, n)
    others = [m['name'] for k, m in M.items() if k != svc['macro']]
    qs.append(make(svc, L, n, T('Which macro work does the service “{svc}” belong to?', 'À quelle macro-œuvre appartient le service « {svc} » ?', 'إلى أي عمل كبير تنتمي خدمة «{svc}»؟'), base, [macro], others[:3],
        T('“{svc}” is one of the services of the macro work “{macro}”.', '« {svc} » est l’un des services de la macro-œuvre « {macro} ».', 'خدمة «{svc}» إحدى خدمات العمل الكبير «{macro}».'), tags=['catalogue']))
    for i in (0, 1):
        n += 1; r = rng_(L, n); d = D[i]
        qs.append(make(svc, L, n, T('Which of these is a deliverable of “{svc}”?', 'Lequel de ces éléments est un livrable de « {svc} » ?', 'أي مما يلي مخرَج من مخرجات خدمة «{svc}»؟'), base | {'d': d['name']}, [d['name']],
            P_NAMES.pick(svc, 3, [x['name'] for x in D], r),
            T('“{d}” is listed among the weekly deliverables of “{svc}”.', '« {d} » figure parmi les livrables hebdomadaires de « {svc} ».', '«{d}» من المخرجات الأسبوعية لخدمة «{svc}».') , tags=['deliverables']) | {})
        qs[-1]['explain'] = fill(T('“{d}” is listed among the weekly deliverables of “{svc}”.', '« {d} » figure parmi les livrables hebdomadaires de « {svc} ».', '«{d}» من المخرجات الأسبوعية لخدمة «{svc}».'), base | {'d': d['name']})
    n += 1; r = rng_(L, n)
    foreign = P_NAMES.pick(svc, 1, [x['name'] for x in D], r, same_macro=0)[0]
    qs.append(make(svc, L, n, T('Which of these is NOT a deliverable of “{svc}”?', 'Lequel de ces éléments n’est PAS un livrable de « {svc} » ?', 'أي مما يلي ليس من مخرجات خدمة «{svc}»؟'), base, [foreign], [d['name'] for d in D[:3]],
        T('One item belongs to another service; the three others are deliverables of “{svc}”.', 'Un élément appartient à un autre service ; les trois autres sont des livrables de « {svc} ».', 'عنصر واحد يخص خدمة أخرى، والثلاثة الباقية من مخرجات «{svc}».'), tags=['deliverables']))
    n += 1; r = rng_(L, n); d = D[2]
    ex = make(svc, L, n, T('In which unit is “{d}” counted for “{svc}”?', 'En quelle unité « {d} » est-il compté pour « {svc} » ?', 'بأي وحدة تُحسب «{d}» في خدمة «{svc}»؟'), base | {'d': d['name']}, [d['unit']], P_UNITS.pick(svc, 3, [x['unit'] for x in D], r),
        T('“{d}” is counted in: {u}.', '« {d} » est compté en : {u}.', '«{d}» تُحسب بوحدة: {u}.'), tags=['deliverables'])
    ex['explain'] = fill(T('“{d}” is counted in: {u}.', '« {d} » est compté en : {u}.', '«{d}» تُحسب بوحدة: {u}.'), base | {'d': d['name'], 'u': d['unit']})
    qs.append(ex)
    n += 1; r = rng_(L, n)
    k = r.choice([2, 3]); true = D[:k] if k == 2 else D[1:4]
    wrong = P_NAMES.pick(svc, 5 - k if k == 2 else 2, [x['name'] for x in D], r, same_macro=0)
    qs.append(make(svc, L, n, T('Select all the deliverables of “{svc}”.', 'Sélectionnez tous les livrables de « {svc} ».', 'اختر كل مخرجات خدمة «{svc}».'), base, [x['name'] for x in true], wrong,
        T('The deliverables of “{svc}” are listed on its service page; the other items belong to different services.', 'Les livrables de « {svc} » sont listés sur sa page de service ; les autres éléments appartiennent à d’autres services.', 'مخرجات «{svc}» مدرجة في صفحة الخدمة، أما العناصر الأخرى فتخص خدمات مختلفة.'), kind='multiple', tags=['deliverables']))
    n += 1
    qs.append(make(svc, L, n, T('For a team working on “{svc}”, what is the minimum number of the 10 eligible weekly reports to submit by Friday 12:00 to meet the pilot threshold?', 'Pour une équipe travaillant sur « {svc} », quel est le nombre minimal des 10 rapports hebdomadaires admissibles à soumettre avant vendredi 12:00 pour atteindre le seuil pilote ?', 'لفريق يعمل على «{svc}»، ما الحد الأدنى من التقارير الأسبوعية المؤهلة العشرة الواجب تقديمها قبل الجمعة 12:00 لبلوغ الحد التجريبي؟'), base,
        [C('9', '9', '9')], [C('5', '5', '5'), C('7', '7', '7'), C('4', '4', '4')],
        T('The pilot threshold is at least 9 of 10 eligible weekly reports, submitted by Friday 12:00.', 'Le seuil pilote est d’au moins 9 rapports hebdomadaires admissibles sur 10, soumis avant vendredi 12:00.', 'الحد التجريبي هو 9 تقارير أسبوعية مؤهلة على الأقل من أصل 10، تُقدَّم قبل الجمعة 12:00.'), tags=['weekly-work']))
    n += 1
    qs.append(make(svc, L, n, T('After you pass a W5D QCM round on “{svc}”, what does your certificate state?', 'Après avoir réussi un QCM W5D sur « {svc} », que déclare votre certificat ?', 'بعد نجاحك في اختبار W5D حول «{svc}»، ماذا تُثبت شهادتك؟'), base,
        [C('What you demonstrated on that test, with a code anyone can verify', 'Ce que vous avez démontré lors de ce test, avec un code que chacun peut vérifier', 'ما أثبتَّه في ذلك الاختبار، برمز يمكن لأي شخص التحقق منه')],
        [C('A guaranteed job at W5D', 'Un emploi garanti chez W5D', 'وظيفة مضمونة لدى W5D'), C('A state diploma', 'Un diplôme d’État', 'شهادة دولة رسمية'), C('A promised investment return', 'Un rendement d’investissement promis', 'عائد استثماري موعود')],
        T('A certificate records a demonstrated result on a test. It is a path, not a job, and it promises neither work nor income.', 'Un certificat consigne un résultat démontré lors d’un test. C’est un parcours, pas un emploi : il ne promet ni travail ni revenu.', 'الشهادة تسجّل نتيجة أثبتها صاحبها في اختبار. هي مسار وليست وظيفة، ولا تعد بعمل ولا بدخل.'), tags=['honesty']))
    n += 1; r = rng_(L, n)
    qs.append(make(svc, L, n, T('Which fields of study does “{svc}” draw on?', 'Sur quels domaines d’études « {svc} » s’appuie-t-il ?', 'على أي مجالات دراسية تعتمد خدمة «{svc}»؟'), base, [svc['fields']], P_FIELDS.pick(svc, 3, [svc['fields']], r),
        T('“{svc}” draws on: {f}.', '« {svc} » s’appuie sur : {f}.', 'تعتمد «{svc}» على: {f}.'), tags=['catalogue']))
    qs[-1]['explain'] = fill(T('“{svc}” draws on: {f}.', '« {svc} » s’appuie sur : {f}.', 'تعتمد «{svc}» على: {f}.'), base | {'f': svc['fields']})
    n += 1; r = rng_(L, n)
    qs.append(make(svc, L, n, T('Where is “{svc}” piloted first?', 'Où « {svc} » est-il piloté en premier ?', 'أين تُجرَّب خدمة «{svc}» أولا؟'), base, [svc['region']], P_REGION.pick(svc, 3, [svc['region']], r),
        T('The pilot sites of “{svc}” are: {g}.', 'Les sites pilotes de « {svc} » sont : {g}.', 'مواقع التجربة لخدمة «{svc}» هي: {g}.'), tags=['catalogue']))
    qs[-1]['explain'] = fill(T('The pilot sites of “{svc}” are: {g}.', 'Les sites pilotes de « {svc} » sont : {g}.', 'مواقع التجربة لخدمة «{svc}» هي: {g}.'), base | {'g': svc['region']})

    # ============================== LEVEL 2: Practitioner (apply the documented rules) ==============================
    L = 2; n = 0
    for i in range(3):
        n += 1; r = rng_(L, n); x = rk(i)
        qs.append(make(svc, L, n, T('According to the risk register of “{svc}”, which mitigation is paired with this risk: “{c}”?', 'Selon le registre des risques de « {svc} », quelle mesure d’atténuation est associée à ce risque : « {c} » ?', 'وفق سجل مخاطر خدمة «{svc}»، ما إجراء التخفيف المقرون بهذا الخطر: «{c}»؟'), base | {'c': x['cause']},
            [x['mit']], P_MIT.pick(svc, 3, [y['mit'] for y in R], r),
            T('The register pairs “{c}” with: {m}', 'Le registre associe « {c} » à : {m}', 'يقرن السجل «{c}» بـ: {m}'), tags=['risk']))
        qs[-1]['explain'] = fill(T('The register pairs “{c}” with: {m}', 'Le registre associe « {c} » à : {m}', 'يقرن السجل «{c}» بـ: {m}'), base | {'c': x['cause'], 'm': x['mit']})
    n += 1; r = rng_(L, n); x = rk(1)
    qs.append(make(svc, L, n, T('In “{svc}”, which risk is mitigated by: “{m}”?', 'Dans « {svc} », quel risque est atténué par : « {m} » ?', 'في «{svc}»، أي خطر يُخفَّف بـ: «{m}»؟'), base | {'m': x['mit']},
        [x['cause']], P_CAUSE.pick(svc, 3, [y['cause'] for y in R], r),
        T('That mitigation is documented for the risk “{c}”.', 'Cette mesure est documentée pour le risque « {c} ».', 'هذا الإجراء موثّق للخطر «{c}».'), tags=['risk']))
    qs[-1]['explain'] = fill(T('That mitigation is documented for the risk “{c}”.', 'Cette mesure est documentée pour le risque « {c} ».', 'هذا الإجراء موثّق للخطر «{c}».'), base | {'c': x['cause']})
    n += 1
    qs.append(make(svc, L, n, T('Which items belong to the acceptance package of “{svc}”?', 'Quels éléments font partie du dossier d’acceptation de « {svc} » ?', 'أي العناصر تدخل في حزمة القبول لخدمة «{svc}»؟'), base,
        [C('A PDF/A acceptance dossier', 'Un dossier d’acceptation PDF/A', 'ملف قبول بصيغة PDF/A'), C('A CSV/JSON source manifest', 'Un manifeste de sources CSV/JSON', 'بيان مصادر بصيغة CSV/JSON')],
        [C('A promise of future income', 'Une promesse de revenus futurs', 'وعد بدخل مستقبلي'), C('A screenshot with no source', 'Une capture d’écran sans source', 'لقطة شاشة بلا مصدر')],
        T('The acceptance package combines a PDF/A dossier and a CSV/JSON source manifest, so every unit can be traced to its source.', 'Le dossier d’acceptation combine un dossier PDF/A et un manifeste de sources CSV/JSON, pour relier chaque unité à sa source.', 'تجمع حزمة القبول ملف PDF/A وبيان مصادر CSV/JSON حتى يمكن ربط كل وحدة بمصدرها.'), kind='multiple', tags=['evidence']))
    n += 1; r = rng_(L, n); dp = BY[svc['depends'][0]['slug']]['title']
    qs.append(make(svc, L, n, T('Which service does “{svc}” depend on?', 'De quel service « {svc} » dépend-il ?', 'على أي خدمة تعتمد «{svc}»؟'), base, [dp],
        P_TITLES.pick(svc, 3, [dp] + [BY[x['slug']]['title'] for x in svc['depends']], r),
        T('“{svc}” depends on “{d}” for its inputs.', '« {svc} » dépend de « {d} » pour ses entrées.', 'تعتمد «{svc}» على «{d}» في مدخلاتها.'), tags=['dependencies']))
    qs[-1]['explain'] = fill(T('“{svc}” depends on “{d}” for its inputs.', '« {svc} » dépend de « {d} » pour ses entrées.', 'تعتمد «{svc}» على «{d}» في مدخلاتها.'), base | {'d': dp})
    n += 1; r = rng_(L, n); fd = BY[svc['feeds'][0]['slug']]['title']
    qs.append(make(svc, L, n, T('Which service does “{svc}” feed with its output?', 'Quel service « {svc} » alimente-t-il avec ses résultats ?', 'أي خدمة تغذّيها «{svc}» بمخرجاتها؟'), base, [fd],
        P_TITLES.pick(svc, 3, [fd] + [BY[x['slug']]['title'] for x in svc['feeds']], r),
        T('“{svc}” feeds “{d}”.', '« {svc} » alimente « {d} ».', 'تغذّي «{svc}» خدمة «{d}».'), tags=['dependencies']))
    qs[-1]['explain'] = fill(T('“{svc}” feeds “{d}”.', '« {svc} » alimente « {d} ».', 'تغذّي «{svc}» خدمة «{d}».'), base | {'d': fd})
    n += 1; r = rng_(L, n); g = svc['sdg'][0]['goal']; own = {x['goal'] for x in svc['sdg']}
    gl = [str(x) for x in range(1, 18) if str(x) not in own]; r.shuffle(gl)
    qs.append(make(svc, L, n, T('Which Sustainable Development Goal (SDG) is linked to “{svc}”?', 'Quel Objectif de développement durable (ODD) est lié à « {svc} » ?', 'أي هدف من أهداف التنمية المستدامة يرتبط بخدمة «{svc}»؟'), base,
        [C('SDG ' + g, 'ODD ' + g, 'الهدف ' + g)], [C('SDG ' + x, 'ODD ' + x, 'الهدف ' + x) for x in gl[:3]],
        T('“{svc}” is mapped to SDG {g}.', '« {svc} » est rattaché à l’ODD {g}.', 'ترتبط «{svc}» بالهدف {g}.'), tags=['sdg']))
    qs[-1]['explain'] = fill(T('“{svc}” is mapped to SDG {g}.', '« {svc} » est rattaché à l’ODD {g}.', 'ترتبط «{svc}» بالهدف {g}.'), base | {'g': g})
    n += 1
    qs.append(make(svc, L, n, T('Which of these are weekly-report rules for “{svc}”?', 'Lesquelles de ces règles s’appliquent aux rapports hebdomadaires de « {svc} » ?', 'أي مما يلي من قواعد التقارير الأسبوعية لخدمة «{svc}»؟'), base,
        [C('At least 9 of 10 eligible reports are submitted by Friday 12:00', 'Au moins 9 rapports admissibles sur 10 sont soumis avant vendredi 12:00', '9 تقارير مؤهلة على الأقل من أصل 10 تُقدَّم قبل الجمعة 12:00'),
         C('No more than 1 evidence-related rework request per team-week', 'Pas plus d’une demande de reprise liée aux preuves par équipe et par semaine', 'لا يزيد طلب إعادة العمل المتعلق بالأدلة عن طلب واحد لكل فريق-أسبوع')],
        [C('Late reports count in full if sent before month end', 'Les rapports tardifs comptent en entier s’ils sont envoyés avant la fin du mois', 'تُحتسب التقارير المتأخرة كاملة إن أُرسلت قبل نهاية الشهر'), C('Rework requests are unlimited when the client agrees', 'Les demandes de reprise sont illimitées si le client est d’accord', 'طلبات إعادة العمل غير محدودة إذا وافق العميل')],
        T('The pilot rules are: at least 9 of 10 eligible reports by Friday 12:00, and at most 1 evidence-related rework request per team-week.', 'Les règles pilotes : au moins 9 rapports admissibles sur 10 avant vendredi 12:00, et au plus 1 demande de reprise liée aux preuves par équipe-semaine.', 'القواعد التجريبية: 9 تقارير مؤهلة على الأقل من 10 قبل الجمعة 12:00، وطلب إعادة عمل واحد على الأكثر متعلق بالأدلة لكل فريق-أسبوع.'), kind='multiple', tags=['weekly-work']))
    n += 1
    qs.append(make(svc, L, n, T('A team member working on “{svc}” is on approved leave this week. What changes in the weekly check?', 'Un membre d’une équipe travaillant sur « {svc} » est en congé approuvé cette semaine. Qu’est-ce qui change dans le contrôle hebdomadaire ?', 'عضو في فريق يعمل على «{svc}» في إجازة معتمدة هذا الأسبوع. ما الذي يتغير في الفحص الأسبوعي؟'), base,
        [C('The eligible denominator changes', 'Le dénominateur admissible change', 'يتغير المقام المؤهل')],
        [C('The 9-of-10 threshold is waived for good', 'Le seuil de 9 sur 10 est supprimé définitivement', 'يُلغى حد 9 من 10 نهائيا'), C('Their reports are marked late', 'Leurs rapports sont marqués en retard', 'تُوسَم تقاريره بالتأخر'), C('Nothing is recorded', 'Rien n’est enregistré', 'لا يُسجَّل أي شيء')],
        T('Approved leave and adjustments change the eligible denominator; the threshold itself stays.', 'Les congés et aménagements approuvés modifient le dénominateur admissible ; le seuil lui-même reste.', 'الإجازات والتعديلات المعتمدة تغيّر المقام المؤهل، أما الحد نفسه فيبقى.'), tags=['weekly-work']))

    # ============================== LEVEL 3: Specialist (cases and evidence) ==============================
    L = 3; n = 0
    n += 1; r = rng_(L, n); b = {l: cap(svc['boundary'][l], l) for l in LANGS}
    qs.append(make(svc, L, n, T('Which statement is the governing boundary of “{svc}”?', 'Quel énoncé est la limite directrice de « {svc} » ?', 'أي عبارة هي الحد الحاكم لخدمة «{svc}»؟'), base, [b],
        [{l: cap(v[l], l) for l in LANGS} for v in P_BOUND.pick(svc, 3, [svc['boundary']], r)],
        T('The governing boundary of “{svc}”: {b}.', 'La limite directrice de « {svc} » : {b}.', 'الحد الحاكم لخدمة «{svc}»: {b}.'), tags=['boundary']))
    qs[-1]['explain'] = fill(T('The governing boundary of “{svc}”: {b}.', 'La limite directrice de « {svc} » : {b}.', 'الحد الحاكم لخدمة «{svc}»: {b}.'), base | {'b': svc['boundary']})
    for i in range(3):
        n += 1; r = rng_(L, n); x = rk(i)
        same = [y['mit'] for y in R if y['mit']['en'] != x['mit']['en']]
        extra = P_MIT.pick(svc, 3, [x['mit']] + same, r)
        wrong = (same[:2] + extra)[:2] + [C('Carry on and document it later', 'Continuer et le documenter plus tard', 'المتابعة وتوثيق الأمر لاحقا')]
        qs.append(make(svc, L, n, T('In a case of “{svc}”, this happens: “{c}”. Which documented response applies?', 'Dans un dossier de « {svc} », ceci se produit : « {c} ». Quelle réponse documentée s’applique ?', 'في حالة من «{svc}» يحدث الآتي: «{c}». أي استجابة موثّقة تنطبق؟'), base | {'c': x['cause']},
            [x['mit']], wrong, T('The documented response is: {m}', 'La réponse documentée est : {m}', 'الاستجابة الموثّقة هي: {m}'), tags=['risk', 'case']))
        qs[-1]['explain'] = fill(T('The documented response is: {m}', 'La réponse documentée est : {m}', 'الاستجابة الموثّقة هي: {m}'), base | {'m': x['mit']})
    n += 1
    qs.append(make(svc, L, n, T('An acceptance dossier for “{svc}” arrives as a PDF/A file but without the CSV/JSON source manifest. What is the correct status?', 'Un dossier d’acceptation de « {svc} » arrive en PDF/A sans le manifeste de sources CSV/JSON. Quel est le statut correct ?', 'يصل ملف قبول لخدمة «{svc}» بصيغة PDF/A دون بيان المصادر CSV/JSON. ما الوضع الصحيح؟'), base,
        [C('Incomplete: ask for the manifest before accepting', 'Incomplet : demander le manifeste avant d’accepter', 'غير مكتمل: اطلب البيان قبل القبول')],
        [C('Accepted: the PDF/A is enough', 'Accepté : le PDF/A suffit', 'مقبول: ملف PDF/A يكفي'), C('Accepted with a price discount', 'Accepté avec une remise', 'مقبول مع خصم في السعر'), C('Rejected for good, no resubmission', 'Rejeté définitivement, sans nouvelle soumission', 'مرفوض نهائيا دون إعادة تقديم')],
        T('The package needs both parts; a missing manifest makes it incomplete, not rejected.', 'Le dossier exige les deux parties ; un manifeste manquant le rend incomplet, pas rejeté.', 'تتطلب الحزمة الجزأين؛ غياب البيان يجعلها غير مكتملة لا مرفوضة.'), tags=['evidence', 'case']))
    n += 1; d = D[0]
    qs.append(make(svc, L, n, T('The acceptance package links “{d}” to which of these?', 'Le dossier d’acceptation relie « {d} » à quels éléments ?', 'بأي من هذه العناصر تربط حزمة القبول «{d}»؟'), base | {'d': d['name']},
        [C('The source', 'La source', 'المصدر'), C('The reviewer', 'Le relecteur', 'المراجع'), C('The exception log', 'Le journal des exceptions', 'سجل الاستثناءات')],
        [C('The investor’s order reference', 'La référence de commande de l’investisseur', 'مرجع طلب المستثمر'), C('Today’s price of a part', 'Le prix du jour d’une part', 'سعر الحصة اليوم')],
        T('The package links each unit to its source, reviewer, version, exception log and authorisation. Orders and prices are not part of it.', 'Le dossier relie chaque unité à sa source, son relecteur, sa version, son journal d’exceptions et son autorisation. Commandes et prix n’en font pas partie.', 'تربط الحزمة كل وحدة بمصدرها ومراجعها ونسختها وسجل استثناءاتها وتفويضها. أما الطلبات والأسعار فليست منها.'), kind='multiple', tags=['evidence']) | {})
    qs[-1]['explain'] = fill(qs[-1]['explain'], base)
    n += 1
    qs.append(make(svc, L, n, T('A team working on “{svc}” has 3 evidence-related rework requests in one week. Against the pilot threshold, this is…', 'Une équipe de « {svc} » a 3 demandes de reprise liées aux preuves en une semaine. Par rapport au seuil pilote, c’est…', 'فريق يعمل على «{svc}» لديه 3 طلبات إعادة عمل متعلقة بالأدلة في أسبوع واحد. بالنسبة للحد التجريبي، هذا…'), base,
        [C('Above the limit of 1: a corrective action is needed', 'Au-dessus de la limite de 1 : une action corrective est nécessaire', 'فوق الحد وهو 1: يلزم إجراء تصحيحي')],
        [C('Within the limit', 'Dans la limite', 'ضمن الحد'), C('Irrelevant if the reports were on time', 'Sans importance si les rapports étaient à l’heure', 'غير مهم إن كانت التقارير في موعدها'), C('A reason to lower the sample rate', 'Une raison de baisser le taux d’échantillonnage', 'سبب لخفض نسبة المعاينة')],
        T('The pilot limit is at most 1 evidence-related rework request per team-week.', 'La limite pilote est d’au plus 1 demande de reprise liée aux preuves par équipe-semaine.', 'الحد التجريبي طلب إعادة عمل واحد على الأكثر متعلق بالأدلة لكل فريق-أسبوع.'), tags=['weekly-work', 'case']))
    n += 1
    qs.append(make(svc, L, n, T('A team on “{svc}” met every threshold, but a critical safety or privacy defect is found. The weekly score is…', 'Une équipe de « {svc} » a atteint tous les seuils, mais un défaut critique de sécurité ou de confidentialité est découvert. Le score hebdomadaire est…', 'فريق على «{svc}» حقق كل الحدود، لكن اكتُشف عيب حرج في السلامة أو الخصوصية. النتيجة الأسبوعية…'), base,
        [C('Not green: the critical defect overrides it', 'Pas vert : le défaut critique prévaut', 'ليست خضراء: العيب الحرج يتجاوزها')],
        [C('Green: thresholds decide alone', 'Vert : les seuils décident seuls', 'خضراء: الحدود وحدها تقرر'), C('Green, with a note', 'Vert, avec une note', 'خضراء مع ملاحظة'), C('Cancelled for the whole programme', 'Annulé pour tout le programme', 'ملغاة للبرنامج كله')],
        T('Critical safety or privacy defects override a green score.', 'Les défauts critiques de sécurité ou de confidentialité prévalent sur un score vert.', 'العيوب الحرجة في السلامة أو الخصوصية تتجاوز أي نتيجة خضراء.'), tags=['integrity', 'case']))
    n += 1; d = D[1]
    qs.append(make(svc, L, n, T('“{svc}” lists a full-capacity pilot target of {t} for “{d}”. How should that figure be read?', '« {svc} » indique pour « {d} » une cible pilote à pleine capacité de {t}. Comment lire ce chiffre ?', 'تذكر «{svc}» هدفا تجريبيا بكامل الطاقة قدره {t} لـ «{d}». كيف يُقرأ هذا الرقم؟'), base | {'d': d['name'], 't': str(d['target'])},
        [C('A staged output from commissioned cases, not a promise', 'Une production par étapes issue de cas commandés, pas une promesse', 'مخرج مرحلي من حالات مكلَّف بها، وليس وعدا')],
        [C('A guarantee delivered every week', 'Une garantie livrée chaque semaine', 'ضمان يُسلَّم كل أسبوع'), C('A minimum paid to each member', 'Un minimum versé à chaque membre', 'حد أدنى يُدفع لكل عضو'), C('A forecast of investor returns', 'Une prévision de rendement pour l’investisseur', 'توقع لعوائد المستثمر')],
        T('Targets are pilot outputs at full, resourced capacity, from commissioned cases. They are not a promise to finish a whole programme each week.', 'Les cibles sont des productions pilotes à pleine capacité dotée, issues de cas commandés. Elles ne promettent pas d’achever un programme entier chaque semaine.', 'الأهداف مخرجات تجريبية بكامل الطاقة الموفَّرة من حالات مكلَّف بها، وليست وعدا بإنجاز برنامج كامل كل أسبوع.'), tags=['honesty']))
    n += 1; d = D[3]
    qs.append(make(svc, L, n, T('Which deliverable of “{svc}” is counted in “{u}”?', 'Quel livrable de « {svc} » est compté en « {u} » ?', 'أي مخرَج من مخرجات «{svc}» يُحسب بوحدة «{u}»؟'), base | {'u': d['unit']},
        [d['name']], [x['name'] for x in D[:3]], T('“{d}” is the deliverable counted in “{u}”.', '« {d} » est le livrable compté en « {u} ».', '«{d}» هو المخرَج الذي يُحسب بوحدة «{u}».'), tags=['deliverables']))
    qs[-1]['explain'] = fill(T('“{d}” is the deliverable counted in “{u}”.', '« {d} » est le livrable compté en « {u} ».', '«{d}» هو المخرَج الذي يُحسب بوحدة «{u}».'), base | {'d': d['name'], 'u': d['unit']})

    # ============================== LEVEL 4: Lead expert (integrity and judgement) ==============================
    L = 4; n = 0
    n += 1
    qs.append(make(svc, L, n, T('What share of the weekly work does the integrity layer sample for “{svc}”?', 'Quelle part du travail hebdomadaire la couche d’intégrité échantillonne-t-elle pour « {svc} » ?', 'ما نسبة العمل الأسبوعي التي تعاينها طبقة النزاهة في «{svc}»؟'), base,
        [C('{{ quality.sampling_percent|pct }}, drawn at random', '{{ quality.sampling_percent|pct }}, tirés au hasard', '{{ quality.sampling_percent|pct }} تُسحب عشوائيا')],
        [C('25%, chosen by the leader', '25 %, choisis par le chef d’équipe', '25% يختارها القائد'), C('50%, for new members only', '50 %, pour les nouveaux membres seulement', '50% للأعضاء الجدد فقط'), C('100%, every week', '100 %, chaque semaine', '100% كل أسبوع')],
        T('The sample rate is fixed by doctrine and random. Lowering it would dilute the integrity layer.', 'Le taux d’échantillonnage est fixé par doctrine et aléatoire. Le baisser diluerait la couche d’intégrité.', 'نسبة المعاينة ثابتة بالمبدأ وعشوائية. وخفضها يُضعف طبقة النزاهة.'), tags=['integrity']))
    n += 1
    qs.append(make(svc, L, n, T('A leader on “{svc}” distrusts one member and wants a higher sample rate for that person only. What is right?', 'Un chef d’équipe de « {svc} » se méfie d’un membre et veut un taux d’échantillonnage plus élevé pour lui seul. Que faire ?', 'قائد في «{svc}» لا يثق بعضو ويريد نسبة معاينة أعلى له وحده. ما الصواب؟'), base,
        [C('Refuse: the sample is fixed and random, not tuned per person', 'Refuser : l’échantillon est fixe et aléatoire, pas réglé par personne', 'الرفض: العينة ثابتة وعشوائية ولا تُضبط لكل شخص')],
        [C('Accept: the leader knows best', 'Accepter : le chef sait mieux', 'القبول: القائد أعلم'), C('Accept for one week only', 'Accepter pour une semaine seulement', 'القبول لأسبوع واحد فقط'), C('Accept if the member agrees', 'Accepter si le membre est d’accord', 'القبول إن وافق العضو')],
        T('The sample rate is not tuned per person. A concern is raised through the exception log and review, not by changing the sample.', 'Le taux n’est pas réglé par personne. Un doute se signale via le journal des exceptions et la revue, pas en changeant l’échantillon.', 'لا تُضبط النسبة لكل شخص. تُرفع المخاوف عبر سجل الاستثناءات والمراجعة لا بتغيير العينة.'), tags=['integrity', 'case']))
    n += 1
    qs.append(make(svc, L, n, T('In how many days does W5D publish a correction after an error is reported on “{svc}”?', 'En combien de jours W5D publie-t-elle une correction après le signalement d’une erreur sur « {svc} » ?', 'في كم يوما تنشر W5D تصحيحا بعد الإبلاغ عن خطأ في «{svc}»؟'), base,
        [C('{{ quality.correction_sla_days }} days', '{{ quality.correction_sla_days }} jours', '{{ quality.correction_sla_days }} يوما')],
        [C('3 days', '3 jours', '3 أيام'), C('45 days', '45 jours', '45 يوما'), C('There is no deadline', 'Il n’y a pas de délai', 'لا يوجد أجل')],
        T('Corrections are published within the declared delay.', 'Les corrections sont publiées dans le délai déclaré.', 'تُنشر التصويبات خلال الأجل المعلن.'), tags=['integrity']))
    n += 1
    qs.append(make(svc, L, n, T('A client of “{svc}” asks whether a W5D part guarantees income. What is the honest answer?', 'Un client de « {svc} » demande si une part W5D garantit un revenu. Quelle est la réponse honnête ?', 'يسأل عميل في «{svc}» هل تضمن حصة W5D دخلا. ما الجواب الصادق؟'), base,
        [C('No: it is an investment in a certified path, not a job, and no return is promised', 'Non : c’est un investissement dans un parcours certifié, pas un emploi, et aucun rendement n’est promis', 'لا: هي استثمار في مسار معتمد وليست وظيفة، ولا يُوعد بأي عائد')],
        [C('Yes, a minimum monthly income', 'Oui, un revenu mensuel minimum', 'نعم، دخل شهري أدنى'), C('Yes, after the first certificate', 'Oui, après le premier certificat', 'نعم، بعد أول شهادة'), C('Yes, if the client signs', 'Oui, si le client signe', 'نعم، إن وقّع العميل')],
        T('W5D never promises income: a part is an investment in a path, not a job.', 'W5D ne promet jamais de revenu : une part est un investissement dans un parcours, pas un emploi.', 'W5D لا تعد بدخل أبدا: الحصة استثمار في مسار لا وظيفة.'), tags=['honesty']))
    n += 1
    qs.append(make(svc, L, n, T('Which of these must a lead expert on “{svc}” refuse to do?', 'Lesquelles de ces actions un expert référent de « {svc} » doit-il refuser ?', 'أي مما يلي يجب أن يرفضه الخبير القائد في «{svc}»؟'), base,
        [C('Sign off work that has no evidence', 'Valider un travail sans preuves', 'اعتماد عمل بلا أدلة'), C('Change the sample rate for one person', 'Modifier le taux d’échantillonnage pour une seule personne', 'تغيير نسبة المعاينة لشخص واحد')],
        [C('Request rework when evidence is missing', 'Demander une reprise quand les preuves manquent', 'طلب إعادة العمل عند غياب الأدلة'), C('Publish a correction within the delay', 'Publier une correction dans le délai', 'نشر تصحيح ضمن الأجل')],
        T('Sign-off needs evidence and the sample is not tuned per person. Asking for rework and publishing corrections are part of the job.', 'La validation exige des preuves et l’échantillon n’est pas réglé par personne. Demander une reprise et publier des corrections font partie du rôle.', 'الاعتماد يتطلب أدلة والعينة لا تُضبط لكل شخص. أما طلب إعادة العمل ونشر التصويبات فمن صميم الدور.'), kind='multiple', tags=['integrity']))
    for i in range(3):
        n += 1; r = rng_(L, n); x = rk(i)
        same = [y['cause'] for y in R if y['cause']['en'] != x['cause']['en']]
        wrong = (same + P_CAUSE.pick(svc, 3, [x['cause']] + same, r))[:3]
        qs.append(make(svc, L, n, T('As lead expert on “{svc}”, you apply this mitigation: “{m}”. Which risk is it answering?', 'En tant qu’expert référent de « {svc} », vous appliquez cette mesure : « {m} ». À quel risque répond-elle ?', 'بصفتك الخبير القائد في «{svc}»، تطبّق هذا الإجراء: «{m}». على أي خطر يرد؟'), base | {'m': x['mit']},
            [x['cause']], wrong, T('That mitigation answers the risk “{c}”.', 'Cette mesure répond au risque « {c} ».', 'هذا الإجراء يرد على الخطر «{c}».'), tags=['risk', 'case']))
        qs[-1]['explain'] = fill(T('That mitigation answers the risk “{c}”.', 'Cette mesure répond au risque « {c} ».', 'هذا الإجراء يرد على الخطر «{c}».'), base | {'c': x['cause']})
    n += 1
    qs.append(make(svc, L, n, T('Which action would breach the boundary of “{svc}” (“{b}”)?', 'Quelle action violerait la limite de « {svc} » (« {b} ») ?', 'أي إجراء يخرق حد «{svc}» («{b}»)؟'), base | {'b': svc['boundary']},
        [C('Signing off a deliverable while ignoring that boundary', 'Valider un livrable en ignorant cette limite', 'اعتماد مخرَج مع تجاهل هذا الحد')],
        [C('Citing the boundary in the dossier', 'Citer la limite dans le dossier', 'ذكر الحد في الملف'), C('Escalating a doubt about the boundary', 'Remonter un doute sur la limite', 'رفع شك بشأن الحد'), C('Recording an exception in the exception log', 'Consigner une exception dans le journal des exceptions', 'تسجيل استثناء في سجل الاستثناءات')],
        T('A boundary is a condition of acceptance: ignoring it breaches it. Citing, escalating and logging exceptions respect it.', 'Une limite est une condition d’acceptation : l’ignorer la viole. La citer, la remonter et journaliser les exceptions la respecte.', 'الحد شرط للقبول: تجاهله يخرقه. أما ذكره ورفعه وتسجيل الاستثناءات فاحترام له.'), tags=['boundary', 'case']))
    n += 1
    qs.append(make(svc, L, n, T('Which statements about the weekly thresholds of “{svc}” are true?', 'Quelles affirmations sur les seuils hebdomadaires de « {svc} » sont vraies ?', 'أي العبارات الآتية عن الحدود الأسبوعية لخدمة «{svc}» صحيحة؟'), base,
        [C('Approved leave changes the eligible denominator', 'Les congés approuvés modifient le dénominateur admissible', 'الإجازات المعتمدة تغيّر المقام المؤهل'), C('A critical safety or privacy defect overrides a green score', 'Un défaut critique de sécurité ou de confidentialité prévaut sur un score vert', 'العيب الحرج في السلامة أو الخصوصية يتجاوز النتيجة الخضراء'), C('They are pilot targets, not established thresholds', 'Ce sont des cibles pilotes, pas des seuils établis', 'هي أهداف تجريبية لا حدود مستقرة')],
        [C('Meeting them guarantees the client accepts the work', 'Les atteindre garantit que le client accepte le travail', 'بلوغها يضمن قبول العميل للعمل')],
        T('The thresholds are pilot targets: leave changes the denominator and critical defects override a green score. Meeting them does not decide the client’s acceptance.', 'Les seuils sont des cibles pilotes : les congés modifient le dénominateur et les défauts critiques prévalent sur un score vert. Les atteindre ne décide pas de l’acceptation du client.', 'الحدود أهداف تجريبية: الإجازات تغيّر المقام والعيوب الحرجة تتجاوز النتيجة الخضراء. وبلوغها لا يحسم قبول العميل.'), kind='multiple', tags=['weekly-work']))
    return qs


def check(q):
    tri = lambda v: all(isinstance(v, dict) and v.get(l, '').strip() for l in LANGS)
    errs = []
    if not re.match(r'^[a-z0-9-]+\.L[1-4]\.\d{3}$', q['id']): errs.append('id')
    if not tri(q['q']): errs.append('q')
    ids = [c['id'] for c in q['choices']]
    if len(ids) < 2 or len(set(ids)) != len(ids): errs.append('choices')
    if not all(tri(c['t']) for c in q['choices']): errs.append('choice text')
    if not q['answer'] or set(q['answer']) - set(ids): errs.append('answer')
    if q['type'] == 'single' and len(q['answer']) != 1: errs.append('single')
    if q['type'] == 'multiple' and not (2 <= len(q['answer']) < len(ids)): errs.append('multiple')
    en = [c['t']['en'] for c in q['choices']]
    if len(set(en)) != len(en): errs.append('dup choice')
    if not tri(q['explain']): errs.append('explain')
    if any('{' in c['t'][l] and '{{' not in c['t'][l] for c in q['choices'] for l in LANGS): errs.append('unfilled')
    if '{svc}' in q['q']['en'] or re.search(r'(?<!\{)\{[a-z]+\}(?!\})', q['q']['en'] + q['explain']['en']): errs.append('unfilled q')
    return errs


def main():
    only_check = '--check' in sys.argv
    tot = bad = 0; types = {'single': 0, 'multiple': 0}
    os.makedirs(f'{ROOT}/data/qcm', exist_ok=True)
    for s in S:
        qs = gen(s)
        per = {}
        for q in qs:
            per[q['level']] = per.get(q['level'], 0) + 1
            e = check(q)
            if e:
                bad += 1; print('INVALID', q['id'], e)
            types[q['type']] += 1
        if per != {1: 10, 2: 10, 3: 10, 4: 10}:
            raise SystemExit(f"{s['slug']}: {per}")
        tot += len(qs)
        if not only_check:
            body = ',\n'.join(json.dumps(q, ensure_ascii=False, separators=(',', ':')) for q in qs)
            open(f"{ROOT}/data/qcm/{s['slug']}.json", 'w', encoding='utf-8').write('{\n"schema": 1,\n"service": ' + json.dumps(s['slug']) + ',\n"speciality": "",\n"questions": [\n' + body + '\n]\n}\n')
    print(f'{tot} questions for {len(S)} services, {bad} invalid, types {types}')
    sys.exit(1 if bad else 0)


if __name__ == '__main__':
    main()
