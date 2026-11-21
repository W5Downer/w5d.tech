#!/usr/bin/env python3
"""Professionalism audit crawler (general -> detailed). stdlib only.

    python3 tests/audit.py http://127.0.0.1:8080 [--report out.json] [--langs en,fr,ar] [--limit N]

Crawls every sitemap URL in every language and checks the details a visitor notices:
status, <html lang/dir>, title/h1/meta description/canonical/og, active menu state (desktop, mobile
menu, tab bar), duplicate ids, images (alt, size), empty links/buttons, form labels, leaked template
tokens / PHP notices, external-link rel, internal link health, untranslated headings, page weight.
Exit code 1 when any 'error' finding exists. Findings are printed grouped by rule.
"""
import concurrent.futures as cf
import collections
import html.parser
import json
import re
import sys
import urllib.error
import urllib.parse
import urllib.request

args = [a for a in sys.argv[1:] if not a.startswith('--')]
BASE = (args[0] if args else 'http://127.0.0.1:8080').rstrip('/')
OPT = {}
for i, a in enumerate(sys.argv):
    if a in ('--report', '--langs', '--limit') and i + 1 < len(sys.argv):
        OPT[a[2:]] = sys.argv[i + 1]
LANGS = OPT.get('langs', 'en,fr,ar').split(',')
RTL = {'ar'}


def fetch(path, lang='en', follow=False):
    req = urllib.request.Request(BASE + path, headers={'Cookie': 'w5d_lang=' + lang, 'User-Agent': 'w5d-audit'})

    class NoRedir(urllib.request.HTTPRedirectHandler):
        def redirect_request(self, *a, **k):
            return None
    op = urllib.request.build_opener(*([] if follow else [NoRedir]))
    try:
        r = op.open(req, timeout=25)
    except urllib.error.HTTPError as e:
        r = e
    st = getattr(r, 'status', None) or r.code
    return st, dict(r.headers), r.read().decode('utf-8', 'replace')


class P(html.parser.HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.tags = []
        self.stack = []
        self.title = ''
        self.h1 = []
        self.meta = {}
        self.link = {}
        self.ids = []
        self.imgs = []
        self.links = []        # (href, text, attrs, in_header, in_footer, in_main)
        self.buttons = []
        self.inputs = []
        self.labels_for = set()
        self.html_attrs = {}
        self.text_chunks = []
        self.in_title = False
        self.cur_h1 = None
        self.cur_a = None
        self.cur_btn = None
        self.region = []
        self.scripts_inline = 0
        self.ext = []
        self.aria_current = []
        self.data_active = []
        self.headings = []
        self.cur_h = None

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        self.stack.append(tag)
        if tag == 'html':
            self.html_attrs = a
        if tag == 'title':
            self.in_title = True
        if tag in ('header', 'footer', 'main', 'nav'):
            self.region.append(tag)
        if tag == 'meta':
            k = a.get('name') or a.get('property')
            if k:
                self.meta[k] = a.get('content', '')
        if tag == 'link' and a.get('rel'):
            self.link[a['rel']] = a.get('href', '')
        if 'id' in a:
            self.ids.append(a['id'])
        if tag == 'h1':
            self.cur_h1 = ''
        if tag in ('h1', 'h2', 'h3', 'h4'):
            self.cur_h = [tag, '']
        if tag == 'img':
            self.imgs.append(a)
        if tag == 'script' and not a.get('src') and a.get('type') not in ('application/ld+json', 'application/json'):
            self.scripts_inline += 1
        if tag == 'a':
            self.cur_a = {'attrs': a, 'text': '', 'region': list(self.region), 'img_alt': ''}
        if tag == 'button':
            self.cur_btn = {'attrs': a, 'text': ''}
        if tag in ('input', 'select', 'textarea'):
            self.inputs.append((tag, a))
        if tag == 'label' and a.get('for'):
            self.labels_for.add(a['for'])
        if 'aria-current' in a:
            self.aria_current.append((tag, a.get('href', ''), list(self.region)))
        if any(k.startswith('data-active') for k in a):
            self.data_active.append((tag, a.get('href', ''), [k for k in a if k.startswith('data-active')]))
        if tag == 'img' and self.cur_a is not None:
            self.cur_a['img_alt'] = a.get('alt', '') or a.get('aria-label', '')
        if tag == 'svg' and self.cur_a is not None and ('aria-label' in a or a.get('role') == 'img'):
            self.cur_a['img_alt'] += a.get('aria-label', '')

    def handle_endtag(self, tag):
        if tag == 'title':
            self.in_title = False
        if tag == 'h1' and self.cur_h1 is not None:
            self.h1.append(self.cur_h1.strip())
            self.cur_h1 = None
        if tag in ('h1', 'h2', 'h3', 'h4') and self.cur_h:
            self.headings.append((self.cur_h[0], self.cur_h[1].strip()))
            self.cur_h = None
        if tag == 'a' and self.cur_a is not None:
            self.links.append(self.cur_a)
            self.cur_a = None
        if tag == 'button' and self.cur_btn is not None:
            self.buttons.append(self.cur_btn)
            self.cur_btn = None
        if tag in ('header', 'footer', 'main', 'nav') and tag in self.region:
            for i in range(len(self.region) - 1, -1, -1):
                if self.region[i] == tag:
                    del self.region[i]
                    break
        if self.stack:
            self.stack.pop()

    def handle_data(self, d):
        if self.in_title:
            self.title += d
        if self.cur_h1 is not None:
            self.cur_h1 += d
        if self.cur_h:
            self.cur_h[1] += d
        if self.cur_a is not None:
            self.cur_a['text'] += d
        if self.cur_btn is not None:
            self.cur_btn['text'] += d
        if 'script' not in self.stack and 'style' not in self.stack:
            self.text_chunks.append(d)


F = []  # findings


def add(sev, rule, url, lang, msg):
    F.append({'sev': sev, 'rule': rule, 'url': url, 'lang': lang, 'msg': msg})


def norm(p):
    u = urllib.parse.urlsplit(p)
    return u.path or '/'


def load_nav():
    nav = json.load(open('data/content/nav/main.json', encoding='utf-8'))
    prefixes, hrefs = [], set()
    for it in nav['items']:
        if it.get('active_prefix'):
            prefixes.append(it['active_prefix'])
        for g in it.get('groups', []):
            for l in g.get('items', []):
                hrefs.add(l['href'].rstrip('/') or '/')
    return prefixes, hrefs


try:
    NAV_PREFIXES, NAV_HREFS = load_nav()
except Exception:
    NAV_PREFIXES, NAV_HREFS = [], set()

LEAK = re.compile(r'(\{\{[^{}]{0,80}\}\}|@php|@foreach|@if\(|<\?php|Notice:|Warning:|Fatal error|Deprecated:|Undefined (variable|index|array key)|(?-i:Array(?! of))|\bNaN\b|\bundefined\b|\[object Object\]|lorem ipsum|TODO|FIXME|\{%|%\})', re.I)
TOKEN_KEY = re.compile(r'\b[a-z]+(\.[a-z_0-9]+){2,}\b')  # untranslated lang keys such as ux.tab_account.foo


def audit_page(path, lang, pages):
    st, h, body = fetch(path, lang)
    if st in (301, 302, 303, 307, 308):
        add('info', 'redirect', path, lang, f'{st} -> {h.get("Location", "")}')
        return None
    if st != 200:
        add('error', 'status', path, lang, f'HTTP {st}')
        return None
    p = P()
    p.feed(body)
    pages[(path, lang)] = p
    ct = h.get('Content-Type', '')
    if 'text/html' not in ct:
        return p
    # html lang/dir
    if p.html_attrs.get('lang') != lang:
        add('error', 'html-lang', path, lang, f'<html lang="{p.html_attrs.get("lang")}">')
    want_dir = 'rtl' if lang in RTL else 'ltr'
    if p.html_attrs.get('dir') != want_dir:
        add('error', 'html-dir', path, lang, f'dir={p.html_attrs.get("dir")} want {want_dir}')
    t = re.sub(r'\s+', ' ', p.title).strip()
    if not t:
        add('error', 'title', path, lang, 'empty <title>')
    elif len(t) > 70:
        add('warn', 'title-long', path, lang, f'{len(t)} chars: {t[:60]}...')
    elif len(t) < 8:
        add('warn', 'title-short', path, lang, t)
    d = p.meta.get('description', '')
    if not d:
        add('error', 'meta-description', path, lang, 'missing')
    elif len(d) < (35 if lang == 'ar' else 50):
        add('warn', 'meta-description', path, lang, f'short ({len(d)})')
    elif len(d) > 175:
        add('warn', 'meta-description', path, lang, f'long ({len(d)})')
    if not p.link.get('canonical'):
        add('error', 'canonical', path, lang, 'missing')
    for k in ('og:title', 'og:description', 'og:image'):
        if not p.meta.get(k):
            add('warn', 'og', path, lang, 'missing ' + k)
    # h1
    if len(p.h1) != 1:
        add('error', 'h1-count', path, lang, f'{len(p.h1)} <h1>')
    elif len(p.h1[0]) < 3:
        add('error', 'h1-empty', path, lang, repr(p.h1[0]))
    # heading order
    last = 0
    for lv, tx in p.headings:
        n = int(lv[1])
        if last and n > last + 1:
            add('warn', 'heading-skip', path, lang, f'{lv} after h{last}: {tx[:40]}')
            break
        last = n
    for lv, tx in p.headings:
        if not tx:
            add('warn', 'heading-empty', path, lang, f'empty <{lv}>')
            break
    # duplicate ids
    dup = [k for k, v in collections.Counter(p.ids).items() if v > 1]
    if dup:
        add('error', 'duplicate-id', path, lang, ', '.join(dup[:6]))
    # images
    for im in p.imgs:
        if 'alt' not in im:
            add('error', 'img-alt', path, lang, im.get('src', '')[:80])
        if not (im.get('width') and im.get('height')) and not im.get('class', '').count('w-') and im.get('src', '').startswith(('/', 'http')):
            add('warn', 'img-size', path, lang, im.get('src', '')[:80])
        if im.get('src', '').startswith('http') and BASE not in im.get('src', ''):
            add('error', 'external-asset', path, lang, im.get('src', '')[:80])
    # links
    seen_empty = 0
    for a in p.links:
        at = a['attrs']
        href = at.get('href')
        txt = re.sub(r'\s+', ' ', a['text']).strip()
        if href is None:
            add('warn', 'a-no-href', path, lang, txt[:40])
            continue
        if href in ('#', '') or href.lower().startswith('javascript:'):
            if 'data-auth-open' not in at and 'data-copy' not in at and not any(k.startswith('data-') for k in at):
                add('warn', 'a-dead-href', path, lang, f'{href!r} {txt[:40]}')
        if not txt and not at.get('aria-label') and not a['img_alt'] and not at.get('title') and 'svg' not in body[:0]:
            seen_empty += 1
        if href.startswith('http') and BASE not in href and urllib.parse.urlsplit(href).netloc != urllib.parse.urlsplit(BASE).netloc:
            rel = at.get('rel', '')
            if at.get('target') == '_blank' and 'noopener' not in rel:
                add('error', 'rel-noopener', path, lang, href[:80])
    if seen_empty:
        add('warn', 'a-no-name', path, lang, f'{seen_empty} link(s) without text or aria-label')
    nb = 0
    for b in p.buttons:
        if not re.sub(r'\s+', '', b['text']) and not b['attrs'].get('aria-label') and not b['attrs'].get('title'):
            nb += 1
    if nb:
        add('warn', 'button-no-name', path, lang, f'{nb} button(s) without text or aria-label')
    # forms
    for tag, a in p.inputs:
        ty = a.get('type', 'text')
        if a.get('aria-hidden') == 'true' or a.get('tabindex') == '-1':
            continue  # honeypot
        if ty in ('hidden', 'submit', 'button', 'checkbox', 'radio', 'image'):
            if ty in ('checkbox', 'radio') and not (a.get('aria-label') or a.get('id') in p.labels_for):
                # often wrapped in <label>; cannot detect wrapping reliably -> info only
                pass
            continue
        if not (a.get('aria-label') or a.get('aria-labelledby') or (a.get('id') and a['id'] in p.labels_for) or a.get('title')):
            add('warn', 'input-label', path, lang, f'{tag} name={a.get("name")} has no id-label/aria-label')
        if tag == 'input' and ty in ('email',) and not a.get('autocomplete'):
            add('info', 'input-autocomplete', path, lang, f'email {a.get("name")}')
    if p.scripts_inline:
        add('error', 'inline-script', path, lang, f'{p.scripts_inline} inline script(s) (CSP)')
    # leaks
    vis = ' '.join(p.text_chunks)
    m = LEAK.search(vis)
    if m:
        s = max(0, m.start() - 30)
        add('error', 'leak', path, lang, repr(vis[s:m.end() + 30]))
    # menu active state
    ap = norm(path).rstrip('/') or '/'
    nav_active = [x for x in p.data_active]
    link_active = [x for x in p.aria_current if 'nav' in x[2] or 'header' in x[2]]
    expect_prefix = [x for x in NAV_PREFIXES if (ap + '/').startswith(x + '/') or ap == x]
    expect_link = ap in NAV_HREFS
    if expect_prefix and not any(k == 'data-active' for _, _, ks in nav_active for k in ks):
        add('error', 'menu-active-trigger', path, lang, f'top-level item {expect_prefix[0]} not marked')
    if expect_link and not any('data-active-link' in ks for _, _, ks in nav_active):
        add('error', 'menu-active-link', path, lang, 'current page link inside mega menu not marked')
    if (expect_prefix or expect_link) and not any(x for x in p.aria_current if x[0] == 'a' or x[0] == 'button' or x[0] == 'li'):
        add('error', 'menu-aria-current', path, lang, 'no aria-current anywhere in navigation for a page that is in the menu')
    # tab bar present
    if 'w5-minibar' in body:
        if 'w5-tabbar' in body or 'nav-solid' in body:
            add('error', 'chromeless', path, lang, 'focused page still renders the navbar/tab bar')
    elif 'w5-tabbar' not in body:
        add('warn', 'tabbar', path, lang, 'bottom tab bar missing')
    elif ap in ('/', '/checkout') or ap.startswith('/account'):
        if not any(x[0] == 'a' and 'tabbar' not in x[2] for x in p.aria_current):
            add('warn', 'tabbar-current', path, lang, 'tab bar has no current item')
    # untranslated: key-like strings
    km = TOKEN_KEY.search(vis)
    if km and not re.search(r'\d', km.group(0)) and '.' in km.group(0) and not re.search(r'\.(com|tech|test|org|net|json|php|png)$', km.group(0)):
        add('warn', 'lang-key-leak', path, lang, km.group(0))
    if len(body) > 400_000:
        add('warn', 'page-weight', path, lang, f'{len(body)//1024} KB HTML')
    return p


def main():
    st, _, sm = fetch('/sitemap.xml')
    locs = re.findall(r'<loc>([^<]+)</loc>', sm)
    paths = []
    for l in locs:
        pth = urllib.parse.urlsplit(l).path or '/'
        if pth not in paths:
            paths.append(pth)
    if OPT.get('limit'):
        paths = paths[:int(OPT['limit'])]
    # also pages the sitemap does not list but visitors reach
    for extra in ('/account/login/', '/account/register/', '/account/forgot/', '/certification/verify/'):
        if extra not in paths:
            paths.append(extra)
    pages = {}
    jobs = [(p, l) for p in paths for l in LANGS]
    with cf.ThreadPoolExecutor(max_workers=8) as ex:
        list(ex.map(lambda j: audit_page(j[0], j[1], pages), jobs))
    # cross-page rules
    for lang in LANGS:
        titles = collections.defaultdict(list)
        h1s = collections.defaultdict(list)
        descs = collections.defaultdict(list)
        for (path, l), p in pages.items():
            if l != lang:
                continue
            titles[re.sub(r'\s+', ' ', p.title).strip()].append(path)
            if p.h1:
                h1s[p.h1[0]].append(path)
            descs[p.meta.get('description', '')].append(path)
        for t, ps in titles.items():
            if t and len(ps) > 1:
                add('warn', 'dup-title', ','.join(ps[:4]), lang, f'{len(ps)} pages share title {t[:50]!r}')
        for t, ps in descs.items():
            if t and len(ps) > 2:
                add('warn', 'dup-description', ','.join(ps[:4]), lang, f'{len(ps)} pages share description')
    # untranslated h1 (fr/ar equal to en) and Arabic script check
    for (path, l), p in list(pages.items()):
        if l == 'en' or not p.h1:
            continue
        pe = pages.get((path, 'en'))
        BRAND = {'MetaX Academy', 'KernID', 'SelfAI', 'WebBooks', 'Studio', '2030B', 'W5D'}
        if p.h1[0] in BRAND:
            continue
        if pe and pe.h1 and pe.h1[0] == p.h1[0] and re.search(r'[A-Za-z]{5,}', p.h1[0]):
            add('warn', 'h1-untranslated', path, l, p.h1[0][:60])
        if l == 'ar' and not re.search(r'[\u0600-\u06FF]', ' '.join(p.h1)):
            add('warn', 'h1-no-arabic', path, l, p.h1[0][:60])
        if l == 'ar' and pe:
            ar_vis = ' '.join(p.text_chunks)
            lat = len(re.findall(r'[A-Za-z]{4,}', ar_vis))
            arb = len(re.findall(r'[\u0600-\u06FF]+', ar_vis))
            if arb and lat > arb * 0.5:
                add('warn', 'ar-latin-heavy', path, l, f'{lat} latin words vs {arb} arabic')
    # internal link health (once each, from en)
    targets = collections.OrderedDict()
    for (path, l), p in pages.items():
        for a in p.links:
            href = a['attrs'].get('href') or ''
            if href.startswith('/') and not href.startswith('//'):
                tp = href.split('#')[0]
                if tp:
                    targets.setdefault(tp, path)
            elif href.startswith(BASE):
                tp = urllib.parse.urlsplit(href).path
                targets.setdefault(tp, path)
        # forms
    def chk(tp):
        s, h, b = fetch(tp, 'en')
        return tp, s, h.get('Location', '')
    with cf.ThreadPoolExecutor(max_workers=8) as ex:
        for tp, s, loc in ex.map(chk, list(targets)[:900]):
            if s == 404:
                add('error', 'broken-link', targets[tp], 'en', f'{tp} -> 404')
            elif s >= 500:
                add('error', 'broken-link', targets[tp], 'en', f'{tp} -> {s}')
    # 404 page quality
    for lang in LANGS:
        s, h, b = fetch('/nope-404-check/', lang)
        if s != 404:
            add('error', '404-status', '/nope-404-check/', lang, f'status {s}')
        else:
            q = P(); q.feed(b)
            if len(q.h1) != 1:
                add('error', '404-h1', '/nope-404-check/', lang, f'{len(q.h1)} h1')
            if 'noindex' not in q.meta.get('robots', ''):
                add('error', '404-robots', '/nope-404-check/', lang, 'missing noindex')
            if not any(a['attrs'].get('href', '').rstrip('/') in ('', '/') or a['attrs'].get('href') in ('/', ) for a in q.links):
                add('warn', '404-home-link', '/nope-404-check/', lang, 'no link back home')
    # summary
    by = collections.defaultdict(list)
    for f in F:
        by[(f['sev'], f['rule'])].append(f)
    order = {'error': 0, 'warn': 1, 'info': 2}
    print(f'audited {len(pages)} page-views ({len(paths)} paths x {len(LANGS)} langs), {len(targets)} internal link targets')
    for (sev, rule), fs in sorted(by.items(), key=lambda kv: (order[kv[0][0]], -len(kv[1]))):
        urls = sorted({f['url'] for f in fs})
        ex = fs[0]
        print(f'[{sev.upper():5}] {rule:22} x{len(fs):4}  e.g. {ex["url"]} ({ex["lang"]}): {ex["msg"][:100]}')
    nerr = sum(1 for f in F if f['sev'] == 'error')
    nwarn = sum(1 for f in F if f['sev'] == 'warn')
    print(f'audit: {nerr} error(s), {nwarn} warning(s)')
    if OPT.get('report'):
        json.dump(F, open(OPT['report'], 'w'), ensure_ascii=False, indent=1)
    sys.exit(1 if nerr else 0)


if __name__ == '__main__':
    main()
