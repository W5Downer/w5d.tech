import json, re, sys, glob, os
ROOT = os.path.join(os.path.dirname(__file__), '..', '..', '..')
def strings(x, lang):
    if isinstance(x, dict):
        if lang in x and isinstance(x[lang], str): yield x[lang]; return
        for k, v in x.items():
            if k in ('id', 'type', 'href', 'icon', 'tier', 'focus', 'gradient', 'variant', 'style', 'cols', 'arrow', 'slug', 'robots'): continue
            yield from strings(v, lang)
    elif isinstance(x, list):
        for v in x: yield from strings(v, lang)
def words(d, lang):
    t = ' '.join(strings(d['sections'], lang))
    t = re.sub(r'\{\{[^}]*\}\}', 'N', t); t = re.sub(r'\]\([^)]*\)', ']', t)
    return len(re.findall(r"[\w][\w'’\-]*", t, re.U))
if __name__ == '__main__':
    for f in sorted(glob.glob(ROOT + '/data/content/pages/trust*.json')) + [ROOT + '/data/content/pages/status.json']:
        d = json.load(open(f)); print('%-22s' % d['slug'], *['%s=%4d' % (l, words(d, l)) for l in ('en', 'fr', 'ar')])
