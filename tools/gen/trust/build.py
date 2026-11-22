import json, os, sys, importlib, pkgutil
sys.path.insert(0, os.path.dirname(__file__)); sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', '..', '..'))
HERE = os.path.dirname(__file__); OUT = os.path.join(HERE, '..', '..', '..', 'data', 'content', 'pages')
from count import words
only = sys.argv[1:]
for m in sorted(pkgutil.iter_modules([HERE]), key=lambda m: m.name):
    if not m.name.startswith('p_'): continue
    mod = importlib.import_module(m.name)
    d = mod.PAGE
    if only and d['slug'] not in only: continue
    if d['slug'] == 'status':   # keep the live funding block: status.json is patched, not replaced
        cur = json.load(open(os.path.join(OUT, 'status.json'))); keep = {s['id']: s for s in cur['sections'] if s['type'] in ('status-live', 'funding-status', 'register')}
        d['sections'] = [keep.get(s, s) if isinstance(s, str) else s for s in d['sections']]
    json.dump(d, open(os.path.join(OUT, d['slug'] + '.json'), 'w'), ensure_ascii=False, indent=2); open(os.path.join(OUT, d['slug'] + '.json'), 'a').write('\n')
    print('%-20s' % d['slug'], *['%s=%d' % (l, words(d, l)) for l in ('en', 'fr', 'ar')])
