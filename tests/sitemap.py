#!/usr/bin/env python3
"""sitemap.xml completeness and hygiene: valid XML, every URL answers 200 without redirect and is indexable, nothing robots.txt
disallows, nothing noindex or guest-gated, every content record present, ISO lastmod never in the future.
Usage: python3 tests/sitemap.py [base_url]
"""
import glob, json, os, re, sys, urllib.request, datetime
import xml.etree.ElementTree as ET
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _prog import *

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))


class NR(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k): return None


def get(path_or_url, headers=None):
    u = path_or_url if path_or_url.startswith('http') else BASE + path_or_url
    try:
        x = urllib.request.build_opener(NR).open(urllib.request.Request(u, headers=headers or {}), timeout=30)
        return x.status, {k.lower(): v for k, v in x.headers.items()}, x.read().decode('utf-8', 'replace')
    except urllib.error.HTTPError as e:
        return e.code, {k.lower(): v for k, v in e.headers.items()}, e.read().decode('utf-8', 'replace')


section('sitemap.xml is valid and complete')
s, h, b = get('/sitemap.xml')
check('200 application/xml', s == 200 and 'xml' in h.get('content-type', ''), f"{s} {h.get('content-type')}")
ns = {'s': 'http://www.sitemaps.org/schemas/sitemap/0.9', 'i': 'http://www.google.com/schemas/sitemap-image/1.1'}
root = ET.fromstring(b)
urls = root.findall('s:url', ns)
locs = [u.find('s:loc', ns).text for u in urls]
paths = [re.sub(r'^https?://[^/]+', '', l) for l in locs]
check('urlset has entries', len(urls) > 150, len(urls))
check('no duplicate URLs', len(set(locs)) == len(locs))
check('every URL absolute and on one origin', len({re.match(r'https?://[^/]+', l).group(0) for l in locs}) == 1)
check('well under the 50,000-URL / 50 MB limits', len(urls) < 50000 and len(b) < 50 * 1024 * 1024)
check('home is first', paths[0] == '/')
today = datetime.date.today().isoformat()
bad = [u.find('s:loc', ns).text for u in urls if not re.fullmatch(r'\d{4}-\d{2}-\d{2}', u.find('s:lastmod', ns).text) or u.find('s:lastmod', ns).text > today]
check('lastmod is ISO and not in the future', not bad, bad[:3])
check('changefreq + priority valid', all(u.find('s:changefreq', ns).text in ('daily', 'weekly', 'monthly') and 0 <= float(u.find('s:priority', ns).text) <= 1 for u in urls))
check('lastmod is per file, not one constant', len({u.find('s:lastmod', ns).text for u in urls}) > 1)
check('no hreflang (language is cookie-based on the same URL)', 'hreflang' not in b)

section('content records are all listed')
want = set()
for coll, base in (('macros', '/macro-works/'), ('services', '/services/'), ('projects', '/projects/')):
    recs = [json.load(open(f, encoding='utf-8')) for f in glob.glob(f'{ROOT}/data/content/{coll}/*.json')]
    miss = [base + r['slug'] + '/' for r in recs if base + r['slug'] + '/' not in paths]
    check(f'every {coll} record present ({len(recs)})', not miss, miss[:3])
    want.add(base)
regions = json.load(open(f'{ROOT}/data/countries/tn.json', encoding='utf-8')).get('regions', [])
check(f'every region present ({len(regions)})', all(f"/tn/{r['slug']}/" in paths for r in regions))
check('index pages present', all(p in paths for p in want))
check('every public audience page present', all(f'/for/{a}/' in paths for a in ('member', 'investor', 'customer', 'user')))
check('private audience doors (owner/admin/leader) are NOT listed', not any(f'/for/{a}/' in paths for a in ('owner', 'admin', 'leader')))
check('certification verify + human sitemap present', '/certification/verify/' in paths and '/sitemap/' in paths)
check('plans page present in XML sitemap', '/plans/' in paths)

section('what must NOT be there')
rob = get('/robots.txt')[2]
dis = [l.split(':', 1)[1].strip() for l in rob.splitlines() if l.lower().startswith('disallow:')]
hit = [p for p in paths if any(p.startswith(d) for d in dis if d)]
check('nothing robots.txt disallows', not hit, hit[:3])
check('robots.txt points at sitemap.xml', re.search(r'(?im)^sitemap:\s*\S+/sitemap\.xml\s*$', rob) is not None)
for p in ('/checkout/done/', '/join/thanks/', '/subscription/manage/', '/account/', '/developers/', '/agents/'):
    check(f'{p} absent', p not in paths)
noidx = [os.path.basename(f) for f in glob.glob(f'{ROOT}/data/content/pages/*.json') if 'noindex' in (json.load(open(f, encoding='utf-8')).get('robots') or '')]
check('noindex pages exist and are excluded', len(noidx) >= 3 and not any(p in paths for p in ('/join/thanks/', '/subscription/manage/', '/checkout/done/')), noidx)
gated = ['/checkout/', '/subscription/', '/app/register/', '/careers/managers/']
red = [p for p in gated if get(p)[0] in (301, 302, 303)]
check('guest-gated forms (redirecting to sign-in) are not listed', not any(p in paths for p in red), red)
check('ungated form pages that stay public are listed', all(p in paths for p in ('/contact/', '/newsletter/')))

section('every listed URL is crawlable and indexable (all 3 languages)')
fails = []
for lang in ('en', 'fr', 'ar'):
    for p in paths:
        st, hd, body = get(p, {'Cookie': f'w5d_lang={lang}'})
        robots_meta = re.search(r'<meta name="robots" content="([^"]*)"', body)
        if st != 200 or 'noindex' in hd.get('x-robots-tag', '') or (robots_meta and 'noindex' in robots_meta.group(1)):
            fails.append((lang, p, st))
        elif not re.search(r'<link rel="canonical" href="https?://[^"]+"', body):
            fails.append((lang, p, 'no canonical'))
check(f'{len(paths)} x 3 languages: 200, indexable, canonical', not fails, fails[:5])
finish('sitemap')
