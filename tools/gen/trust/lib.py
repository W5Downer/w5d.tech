"""Tiny DSL for the trust pages. Every text is a 3-tuple (en, fr, ar). Build: python3 tools/gen/trust/build.py"""
L = ('en', 'fr', 'ar')

def T(x):
    if isinstance(x, dict): return x
    assert isinstance(x, (tuple, list)) and len(x) == 3, 'need (en, fr, ar): %r' % (x,)
    return dict(zip(L, x))

def sec(type_, id_, tier='e', focus=2, **kw):
    d = {'type': type_, 'id': id_}
    if type_ not in ('hero', 'cta'): d.update(tier=tier, focus=focus)
    for k, v in kw.items():
        if v is None: continue
        d[k] = v
    return d

def cta(label, href, style='ghost', arrow=False):
    d = {'label': T(label), 'href': href, 'style': style}
    if arrow: d['arrow'] = True
    return d

def hero(id_, title, lead, eyebrow, ctas=(), gradient='calm'):
    return {'type': 'hero', 'id': id_, 'variant': 'classic', 'gradient': gradient, 'title': T(title), 'lead': T(lead),
            'ctas': [cta(*c) if isinstance(c, tuple) and isinstance(c[0], tuple) else c for c in ctas], 'eyebrow': T(eyebrow)}

def prose(id_, title, paras, lst=None, ctas=(), tier='e', focus=2, lead=None):
    return sec('prose', id_, tier, focus, title=T(title), lead=T(lead) if lead else None, paragraphs=[T(p) for p in paras],
               list=[T(x) for x in lst] if lst else None, ctas=[cta(*c) for c in ctas] or None)

def defs(id_, title, items, tier='x', focus=2, lead=None):
    return sec('definitions', id_, tier, focus, title=T(title), lead=T(lead) if lead else None,
               items=[{'term': T(a), 'def': T(b)} for a, b in items])

def faq(id_, title, items, tier='i', focus=2):
    return sec('faq', id_, tier, focus, title=T(title), items=[{'q': T(q), 'a': T(a)} for q, a in items])

def table(id_, title, cols, rows, note=None, tier='e', focus=2, lead=None):
    return sec('table', id_, tier, focus, title=T(title), lead=T(lead) if lead else None, columns=[T(c) for c in cols],
               rows=[[T(c) for c in r] for r in rows], note=T(note) if note else None)

def steps(id_, title, items, tier='i', focus=2):
    return sec('steps', id_, tier, focus, title=T(title), items=[{'title': T(a), 'body': T(b)} for a, b in items])

def timeline(id_, title, items, tier='i', focus=2):
    return sec('timeline', id_, tier, focus, title=T(title), items=[{'when': T(a), 'title': T(b), 'body': T(c)} for a, b, c in items])

def cards(id_, title, items, cols=3, tier='e', focus=3, lead=None):
    return sec('cards', id_, tier, focus, cols=cols, title=T(title), lead=T(lead) if lead else None,
               items=[{'icon': ic, 'title': T(t), 'body': T(b), 'href': h, 'cta': T(('Read', 'Lire', 'اقرأ'))} for ic, t, b, h in items])

def cta_block(id_, title, body, ctas, gradient='peak'):
    return {'type': 'cta', 'id': id_, 'gradient': gradient, 'title': T(title), 'body': T(body) if body else T(('', '', '')),
            'ctas': [cta(*c) for c in ctas]}

def page(slug, title, desc, sections, robots=None):
    d = {'slug': slug, 'title': T(title), 'description': T(desc), 'sections': sections}
    if robots: d['robots'] = robots
    return d

# Shared footer-of-page sentence (a caveat every legal page carries)
VERIFY = ('This page is a plain-language statement of how W5D operates. It is not legal advice, and it is reviewed against Tunisian law with a qualified Tunisian lawyer and accountant before it is relied on. Last review: {{ dates.as_of }}; reviewed at least every {{ legal.review_months }} months.',
          'Cette page est une déclaration en langage clair de la façon dont W5D fonctionne. Elle ne constitue pas un avis juridique ; elle est confrontée au droit tunisien avec un avocat et un comptable tunisiens qualifiés avant qu’on s’y fie. Dernière revue : {{ dates.as_of }} ; revue au moins tous les {{ legal.review_months }} mois.',
          'هذه الصفحة عرض مبسّط لطريقة عمل W5D، وليست استشارة قانونية؛ وتُراجَع على ضوء القانون التونسي مع محامٍ ومحاسب تونسيين مؤهلين قبل الاعتماد عليها. آخر مراجعة: {{ dates.as_of }}؛ وتُراجَع مرة كل {{ legal.review_months }} أشهر على الأقل.')
