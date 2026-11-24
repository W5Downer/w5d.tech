#!/usr/bin/env python3
"""FR/AR consistency checker. Usage: python3 tests/i18n.py [base_url]
A machine cannot judge whether French or Arabic reads NATURALLY (that needs a native speaker: see docs/review-checklist.md).
It CAN catch the mistakes that make translated text wrong or dangerous. For every en/fr/ar triple in:
  - every inline tr3('en','fr','ar') in views/templates (pure string literals),
  - every entry of config/help.php,
  - the new/changed content sections (home-purpose, pv-google, tm-content) and every leaf of the trust pages,
  - the rendered owner Setup guide in the three languages (same steps, same variables, same links),
the three versions must agree on: numbers, URLs and paths, `code` tokens and ENV_VAR names, :placeholders and {{ tokens }};
Arabic must be written in Arabic script, French must not be a copy of the English, lengths must be plausible (an empty or
truncated translation shows up as an outlier). Brand and technical terms listed in KEEP may stay Latin in Arabic."""
import glob, html as _h, json, os, re, sys
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _acct import _client, _call
from _staff import ROOT, MOUNT, staff_login
BASE = next((a for a in sys.argv[1:] if a.startswith('http')), 'http://127.0.0.1:8080').rstrip('/')
ok = bad = 0
fails = []
def check(n, c, extra=''):
    global ok, bad
    if c: ok += 1
    else:
        bad += 1; print('  FAIL', n, extra)

AR = re.compile(r'[\u0600-\u06FF]')
LAT = re.compile(r'[A-Za-z]')
STOP_EN = set('the a an and or of to in on for with is are be this that your you it as at by from not no can will'.split())

def strip_html(s):
    return _h.unescape(re.sub(r'<[^>]+>', ' ', s))

def plain(s):
    """text without things that are not language: {{ tokens }}, the brand name, identifiers (snake_case / dotted.names), code spans"""
    s = re.sub(r'\{\{.*?\}\}', ' ', s)
    s = re.sub(r'`[^`]*`', ' ', s)
    s = re.sub(r'W5D|MetaX Academy|MetaX|Tunis|INS\b|OECD', ' ', s)
    s = re.sub(r'\b[\w]+(?:[_.][\w]+)+\b', ' ', s)
    return s

def nums(s):
    s = plain(s)
    s = re.sub(r'https?://\S+|/[\w\-./]+', ' ', s)           # digits inside URLs/paths are compared as URLs
    s = s.translate(str.maketrans('٠١٢٣٤٥٦٧٨٩', '0123456789'))   # Arabic-Indic digits count as the same number
    s = re.sub(r'\b\d+D\b', ' ', s)                              # "3D" is written "ثلاثي الأبعاد" in Arabic and "3D" in French: a name, not a quantity
    s = re.sub(r'(\d{1,2}):00\b', r'\1', s)                      # "12:00" and "12 h" are the same time
    s = re.sub(r'\b[QT][1-4]\b', ' ', s)                       # "Q2" / "T2" are spelled out as words in Arabic ("الربع الثاني")
    s = re.sub(r'(?<=\d)[\u00a0\u202f\u2009 ,.](?=\d{3}\b)', '', s)      # 1,000 / 1 000 / 1.000 -> 1000
    s = re.sub(r'(?<=\d)[,.](?=\d{1,2}\b)', '.', s)             # 26,6 -> 26.6
    # the numeral 1 is skipped: 'one' / 'un' / 'واحد' are routinely written as words (and '1 minute' vs 'دقيقة واحدة')
    return sorted(n for n in re.findall(r'\d+(?:\.\d+)?', s) if n != '1')

def urls(s):
    return sorted(set(m.rstrip('.,;:)') for m in re.findall(r'https?://[^\s"\'<>)]+|(?<![\w/])/[a-z][\w\-]*(?:/[\w\-.#?=&]*)*', s)))

def code_tokens(s, strict=True):
    toks = set(re.findall(r'\b[A-Z][A-Z0-9]{2,}(?:_[A-Z0-9]+)+\b', s))        # ENV_VARS
    toks |= set(re.findall(r'(?<![\w/])\.env\b|\bphp bin/[\w\-.]+(?: [\w\-@.]+)*', s))
    toks |= set(re.findall(r'\bdata-[a-z]+(?:-[a-z]+)*(?==)', s))                # data-attr="..." only
    if strict: toks |= set(re.findall(r'`([^`]+)`', s))                        # content pages translate `service` labels by design: strict only for UI texts
    return toks

def holders(s):
    return sorted(set(re.findall(r':[a-z_]{2,}\b', s)) | set(re.findall(r'\{\{\s*[\w.]+\s*\}\}', s)) | set(re.findall(r'%[sd]', s)))

def words(s):
    return re.findall(r"[A-Za-zÀ-ÿ']+", s.lower())

def triple_problems(en, fr, ar, where, allow_same=False, strict=True):
    """-> list of problem strings for this triple"""
    P = []
    ten, tfr, tar = strip_html(en), strip_html(fr), strip_html(ar)
    if not (ten.strip() or tfr.strip() or tar.strip()):
        return []                                            # unused placeholder leaf (all three empty)
    if not (ten.strip() and tfr.strip() and tar.strip()):
        return [f'{where}: an empty language']
    prose = plain(strip_html(en))
    has_words = len(re.findall(r'[A-Za-z]{3,}', re.sub(r'https?://\S+|/[\w\-./]+', ' ', prose))) >= 1
    if nums(ten) != nums(tfr): P.append(f'numbers differ en/fr {nums(ten)} vs {nums(tfr)}')
    if nums(ten) != nums(tar): P.append(f'numbers differ en/ar {nums(ten)} vs {nums(tar)}')
    ue, uf, ua = urls(en), urls(fr), urls(ar)
    if ue != uf: P.append(f'links differ en/fr {ue} vs {uf}')
    if ue != ua: P.append(f'links differ en/ar {ue} vs {ua}')
    ce, cf, ca = code_tokens(en, strict), code_tokens(fr, strict), code_tokens(ar, strict)
    if ce != cf: P.append(f'code tokens differ en/fr {sorted(ce ^ cf)}')
    if ce != ca: P.append(f'code tokens differ en/ar {sorted(ce ^ ca)}')
    he, hf, ha = holders(en), holders(fr), holders(ar)
    if he != hf or he != ha: P.append(f'placeholders differ {he} / {hf} / {ha}')
    # script checks
    letters_ar = len(AR.findall(tar))
    latin_ar = LAT.findall(re.sub(r'https?://\S+|/[\w\-./#?=&]+|[A-Z][A-Za-z0-9_.=\-]*|\.env|php bin/\S+|\b(?:php|bin|sh|sqlite|env|cache|view|lint|preflight|json|csv|pdf|api|url|id|ok)\b', ' ', plain(tar)))
    if has_words and len(prose) >= 12 and letters_ar < 2 and not allow_same: P.append('arabic text has no Arabic script')
    if letters_ar >= 2 and len(latin_ar) > max(12, 0.35 * letters_ar): P.append(f'arabic text is mostly Latin ({len(latin_ar)} Latin letters left)')
    we = [w for w in words(plain(ten)) if w not in STOP_EN]
    wf = words(plain(tfr))
    if has_words and len(we) >= 4 and not allow_same:
        if plain(ten).strip().lower() == plain(tfr).strip().lower(): P.append('french is a copy of the english')
        else:
            eset = set(words(plain(ten))); fset = set(wf)
            overlap = len(eset & fset) / max(1, len(fset))
            if overlap > 0.85 and len(fset) >= 6: P.append(f'french shares {int(overlap*100)}% of its words with english (untranslated?)')
    # length plausibility (HTML stripped, whitespace collapsed)
    le, lf, la = (len(re.sub(r'\s+', ' ', x).strip()) for x in (ten, tfr, tar))
    if le >= 60:
        if not (0.45 <= lf / le <= 2.4): P.append(f'french length {lf} vs english {le}')
        if not (0.25 <= la / le <= 2.2): P.append(f'arabic length {la} vs english {le}')
    return P

# Reviewed differences that are correct, not translation errors. Each entry: (leaf-path regex, problem prefix, reason).
KNOWN = [
    (r'services/[\w-]+\.json\.short$', 'numbers differ', "English 'short' is the generated line 'The service provides …'; the FR/AR 'short' is a hand-written one-sentence summary that does not repeat the standard/number"),
    (r'services/[\w-]+\.json\.kpi$', 'numbers differ en/ar', "Arabic writes the number 2 as the dual noun (ملفين = 'two dossiers')"),
]
def known(where, problem):
    return any(re.search(p, where) and problem.startswith(pre) for p, pre, _ in KNOWN)

def report(label, items):
    """items: list of (where, [problems])"""
    n = len(items)
    items = [(w, [x for x in p if not known(w, x)]) for w, p in items]
    badl = [(w, p) for w, p in items if p]
    check(f'{label}: {n} triples checked, all consistent', not badl, '\n    ' + '\n    '.join(f'{w}: {"; ".join(p)}' for w, p in badl[:60]) + (f'\n    … and {len(badl) - 60} more' if len(badl) > 60 else ''))
    return n

# ---------------------------------------------------------------- literal parsing for tr3('..','..','..')
def parse_literal(s, i):
    """parse a PHP string literal starting at s[i] ('...' or "..."); -> (value, next_index) or None"""
    while i < len(s) and s[i] in ' \t\r\n': i += 1
    if i >= len(s) or s[i] not in '\'"': return None
    q = s[i]; i += 1; out = []
    while i < len(s):
        c = s[i]
        if c == '\\' and i + 1 < len(s):
            nx = s[i + 1]
            if q == "'": out.append(nx if nx in "\\'" else '\\' + nx)
            else: out.append({'n': '\n', 't': '\t', '"': '"', '\\': '\\', '$': '$'}.get(nx, '\\' + nx))
            i += 2; continue
        if c == q: return ''.join(out), i + 1
        out.append(c); i += 1
    return None

def tr3_calls(text):
    for m in re.finditer(r'\btr3\(', text):
        i = m.end(); vals = []
        for k in range(3):
            r = parse_literal(text, i)
            if not r: break
            v, i = r; vals.append(v)
            while i < len(text) and text[i] in ' \t\r\n': i += 1
            if k < 2:
                if i < len(text) and text[i] == ',': i += 1
                else: break
        else:
            # pure literals only: a concatenation or variable after the third literal means it is not a plain triple
            while i < len(text) and text[i] in ' \t\r\n': i += 1
            if i < len(text) and text[i] in ',)':
                yield text.count('\n', 0, m.start()) + 1, vals

items = []
for f in sorted(glob.glob(os.path.join(ROOT, 'views/templates/**/*.php'), recursive=True)):
    txt = open(f, encoding='utf-8').read()
    for ln, (en, fr, ar) in tr3_calls(txt):
        items.append((f'{os.path.relpath(f, ROOT)}:{ln}', triple_problems(en, fr, ar, f)))
n_tr3 = report('inline tr3() in templates', items)
check('found a meaningful number of tr3 triples', n_tr3 > 300, str(n_tr3))

# ---------------------------------------------------------------- help.php
import subprocess
help_json = subprocess.run(['php', '-r', "define('ROOT', getcwd()); echo json_encode(require 'config/help.php', JSON_UNESCAPED_UNICODE);"], cwd=ROOT, capture_output=True, text=True).stdout
H = json.loads(help_json)
items = [(k, triple_problems(v[1], v[2], v[3], k)) for k, v in H.items()]
report('config/help.php', items)
check('every help entry has mode tip|pop and 3 non-empty texts', all(v[0] in ('tip', 'pop') and all(str(x).strip() for x in v[1:4]) for v in H.values()))
check('no help text promises money, returns or guarantees', not [k for k, v in H.items() if re.search(r'guaranteed|garanti(?:e|s|es)? (?:de |d’|d\')?(?:rendement|gain|revenu|emploi)|عائد مضمون|ضمان العائد|ضمان الوظيفة|returns? on (?:your|the) (?:money|contribution)', ' '.join(v[1:4]), re.I)])

# ---------------------------------------------------------------- content JSON: every {en,fr,ar} leaf, with focus on the new sections
def leaves(o, path=''):
    if isinstance(o, dict):
        if set(o) >= {'en', 'fr', 'ar'} and all(isinstance(o[k], str) for k in ('en', 'fr', 'ar')):
            yield path, o
        else:
            for k, v in o.items(): yield from leaves(v, f'{path}.{k}')
    elif isinstance(o, list):
        for i, v in enumerate(o): yield from leaves(v, f'{path}[{i}]')

NEW = {'home.json': ['home-purpose'], 'trust-privacy.json': ['pv-google'], 'trust-terms.json': ['tm-content']}
items = []; items_new = []
for f in sorted(glob.glob(os.path.join(ROOT, 'data/content/**/*.json'), recursive=True)):
    try: d = json.load(open(f, encoding='utf-8'))
    except Exception: continue
    base = os.path.basename(f)
    for path, o in leaves(d):
        probs = triple_problems(o['en'], o['fr'], o['ar'], path, strict=False)
        row = (f'{os.path.relpath(f, ROOT)}{path}', probs)
        items.append(row)
        for sid in NEW.get(base, []):
            sec = next((s for s in d.get('sections', []) if s.get('id') == sid), None)
            if sec is not None and any(path.startswith(f'.sections[{i}]') for i, s in enumerate(d['sections']) if s is sec): items_new.append(row)
report('content JSON leaves (all pages)', items)
n = report('new Google/NCII sections (home-purpose, pv-google, tm-content)', items_new)
check('new sections were located (3 sections x several leaves)', n >= 12, str(n))

# ---------------------------------------------------------------- structure of the new sections: same paragraph count, same links per language
for base, sid in (('home.json', 'home-purpose'), ('trust-privacy.json', 'pv-google'), ('trust-terms.json', 'tm-content')):
    d = json.load(open(os.path.join(ROOT, 'data/content/pages', base), encoding='utf-8'))
    sec = next(s for s in d['sections'] if s['id'] == sid)
    for lang in ('en', 'fr', 'ar'):
        pass
    body = sec.get('body') or sec.get('paragraphs') or []
    if isinstance(body, list):
        counts = {l: sum(1 for p in body if isinstance(p, dict) and p.get(l)) for l in ('en', 'fr', 'ar')}
        check(f'{sid}: same number of paragraphs in en/fr/ar', len(set(counts.values())) == 1, str(counts))
    flat = json.dumps(sec, ensure_ascii=False)
    check(f'{sid}: no hard-coded amounts', not re.search(r'\d[\d,. ]*\s*(TND|DT|dinars)', flat))

# ---------------------------------------------------------------- the rendered Setup guide: same skeleton in three languages
def setup_page(lang):
    c = staff_login(BASE, ROOT)
    _call(c, BASE, 'GET', f'/language/{lang}?r=/')
    s, h, b = _call(c, BASE, 'GET', MOUNT + '/setup-guide/')
    return s, b
pages = {}
for lang in ('en', 'fr', 'ar'):
    s, b = setup_page(lang)
    check(f'[{lang}] setup guide renders', s == 200 and 'data-setup-guide' in b, str(s))
    pages[lang] = b
def skeleton(b):
    main = re.search(r'<main.*?</main>', b, re.S); m = main.group(0) if main else b
    steps = re.findall(r'<(?:li|section|article)[^>]*\bid="(step-[\w\-]+|[\w\-]*setup[\w\-]*)"', m)
    codes = sorted(set(re.findall(r'<code[^>]*>([^<]+)</code>', m)))
    links = sorted(set(re.sub(r'#.*$', '', h) for h in re.findall(r'href="([^"#][^"]*)"', m) if not h.startswith('/language/')))
    nums_ = nums(strip_html(m))
    return steps, codes, links, nums_, len(re.findall(r'<h[23][ >]', m)), len(re.findall(r'<li[ >]', m))
sk = {l: skeleton(b) for l, b in pages.items()}
check('setup guide: same step ids in en/fr/ar', sk['en'][0] == sk['fr'][0] == sk['ar'][0] and len(sk['en'][0]) >= 8, str({l: len(v[0]) for l, v in sk.items()}))
check('setup guide: same <code> tokens (variables, commands, paths) in en/fr/ar', sk['en'][1] == sk['fr'][1] == sk['ar'][1], str(set(sk['en'][1]) ^ set(sk['fr'][1])) + ' | ' + str(set(sk['en'][1]) ^ set(sk['ar'][1])))
check('setup guide: same links in en/fr/ar', sk['en'][2] == sk['fr'][2] == sk['ar'][2], str(set(sk['en'][2]) ^ set(sk['fr'][2])) + ' | ' + str(set(sk['en'][2]) ^ set(sk['ar'][2])))
check('setup guide: same headings and list items in en/fr/ar', sk['en'][4:] == sk['fr'][4:] == sk['ar'][4:], str({l: v[4:] for l, v in sk.items()}))
check('setup guide: same numbers in en/fr/ar', sk['en'][3] == sk['fr'][3] == sk['ar'][3], f"{sk['en'][3][:12]} | {sk['fr'][3][:12]} | {sk['ar'][3][:12]}")
txt = {l: re.sub(r'\s+', ' ', strip_html(re.search(r'<main.*?</main>', b, re.S).group(0))) for l, b in pages.items()}
check('setup guide (ar): written in Arabic script', len(AR.findall(txt['ar'])) > 2000)
check('setup guide (fr): not English text', len(set(words(txt['fr'])) & set(words(txt['en']))) / max(1, len(set(words(txt['fr'])))) < 0.5)
check('setup guide: no unresolved template syntax in any language', not any(re.search(r'\{\{|\}\}|@foreach|@if\b|tr3\(', t) for t in txt.values()))

# ---------------------------------------------------------------- language switch keeps the page: dir attribute and lang attribute
for lang, d in (('en', 'ltr'), ('fr', 'ltr'), ('ar', 'rtl')):
    c = _client(); _call(c, BASE, 'GET', f'/language/{lang}?r=/')
    s, h, b = _call(c, BASE, 'GET', '/')
    m = re.search(r'<html[^>]*>', b)
    check(f'[{lang}] <html> has lang="{lang}" dir="{d}"', bool(m) and f'lang="{lang}"' in m.group(0) and f'dir="{d}"' in m.group(0), m.group(0) if m else '')

print(f'\ni18n: {ok} passed, {bad} failed')
sys.exit(1 if bad else 0)
