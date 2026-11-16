"""Content-gen helpers. Everything is authored in English here; fr/ar are filled by translate.py
(or given explicitly where a native string exists)."""
import json, os
ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
C = os.path.join(ROOT, 'data/content')

def L(en, fr=None, ar=None):
    d = {'en': en}
    if fr: d['fr'] = fr
    if ar: d['ar'] = ar
    return d

def cta(label, href, style='focus', arrow=True, icon=None, id=None):
    d = {'label': L(label), 'href': href, 'style': style}
    if arrow: d['arrow'] = True
    if icon: d['icon'] = icon
    if id: d['id'] = id
    return d

def write(coll, slug, obj):
    p = os.path.join(C, coll)
    os.makedirs(p, exist_ok=True)
    obj = {'slug': slug, **obj} if 'slug' not in obj else obj
    with open(os.path.join(p, slug + '.json'), 'w', encoding='utf-8') as f:
        json.dump(obj, f, ensure_ascii=False, indent=1); f.write('\n')

def sec(type, id, tier='i', focus=2, **kw):
    d = {'type': type, 'id': id, 'tier': tier, 'focus': focus}
    for k, v in kw.items():
        d[k] = L(v) if isinstance(v, str) and k in ('title', 'lead', 'eyebrow', 'body', 'note', 'text', 'by') else v
    return d

def item(**kw):
    return {k: (L(v) if isinstance(v, str) and k in ('title', 'body', 'label', 'cta', 'tag', 'meta', 'term', 'def', 'q', 'a', 'when', 'name', 'status', 'evidence', 'date', 'count', 'desc') else v) for k, v in kw.items()}
