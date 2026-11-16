#!/usr/bin/env python3
"""Parse data/services-dossier.md + data/services.md into structured per-service records.
Output: tools/gen/_parsed_services.json (intermediate, not shipped)."""
import re, json, sys, pathlib
ROOT = pathlib.Path(__file__).resolve().parents[2]
ds = (ROOT/'data/services-dossier.md').read_text(encoding='utf-8')
sv = (ROOT/'data/services.md').read_text(encoding='utf-8')

def clean(s):
    s = re.sub(r'\s*\[[A-Z]\d{2}(?:[;,–\- ]+[A-Z]?\d{2})*\]', '', s)   # citation tags [W01; W02]
    return re.sub(r'\s+', ' ', s).strip()

def field(body, label, nxt=r'\n\*\*|\n---'):
    m = re.search(r'\*\*' + re.escape(label) + r'[^*]*\*\*\s*\n?(.*?)(?=' + nxt + r')', body, re.S)
    return m.group(1).strip() if m else ''

svc_md = {}
for m in re.finditer(r'### (\d{3}) · (.*?)\r?\n`([^`]+)` · \*\*([^*]+)\*\* · (\w+)\r?\n\r?\n\*\*Short:\*\* (.*?)\r?\n\r?\n\*\*Long:\*\* (.*?)\r?\n', sv):
    svc_md[m.group(1)] = dict(title=m.group(2).strip(), slug=m.group(3), placement=m.group(4), macro=m.group(5), short=m.group(6).strip(), long=m.group(7).strip())

parts = re.split(r'\n### #(\d{3}) · (.*?)\n', ds)
out = {}
for i in range(1, len(parts), 3):
    sid, title, body = parts[i], parts[i+1].strip(), parts[i+2]
    r = {'id': int(sid), 'title_en': title}
    r['slug'] = re.search(r'\*\*Slug\*\* · `([^`]+)`', body).group(1)
    r['macro_name'] = re.search(r'\*\*Macro work\*\* · (.*)', body).group(1).strip()
    r['placement'] = re.search(r'\*\*Placement\*\* · (.*?)\s*\n', body).group(1).strip()
    dem = re.search(r'\*\*Registered demand, Tunisia\*\* · (.*?)\n\n', body, re.S).group(1)
    fm = re.search(r'unauthenticated|ANETI speciality count: (.*?); diploma levels (.*?); priority cohort (.*?); proposed region: (.*?)(?:\.|$)', dem)
    m = re.search(r'ANETI speciality count: (.*?); diploma levels (.*?); priority cohort (.*?); proposed region: (.*?)\. ', dem + ' ')
    if m:
        r['fields'], r['diploma'], r['cohort'], r['region'] = [clean(x) for x in m.groups()]
    r['short_en'] = clean(re.search(r'\*\*Short description \(EN[^)]*\)\.\*\*\s*\n(.*?)\n', body).group(1))
    r['short_fr'] = clean(re.search(r'\*\*Short description \(FR\)\.\*\*\s*\n(.*?)\n', body).group(1))
    r['short_ar'] = clean(re.search(r'\*\*Short description \(AR\)\.\*\*\s*\n(.*?)\n', body).group(1))
    lg = re.search(r'\*\*Long description \(EN[^)]*\)\.\*\*\s*\n(.*?)\n\n\*\*Problem', body, re.S).group(1)
    r['long_en'] = [clean(p) for p in lg.split('\n\n') if p.strip()]
    # deliverables table
    rows = re.findall(r'^\| (.+?) \| (.+?) \| ([\d,\.]+) \(proposed\) \|', body, re.M)
    r['deliverables'] = [dict(name=clean(a).replace(', accepted against rubric', ''), unit=clean(b), target=int(c.replace(',', ''))) for a, b, c in rows]
    ev = re.search(r'\*\*Evidence rule[^*]*\*\*\s*\n(.*?)\n\n', body, re.S)
    r['evidence_en'] = clean(ev.group(1)).split(' **Proposed storage:**')[0] if ev else ''
    kp = re.search(r'\*\*Weekly KPI[^*]*\*\*\s*\n(.*?)\n\n', body, re.S)
    r['kpi_en'] = clean(kp.group(1)) if kp else ''
    dp = re.search(r'\*\*Cross-macro dependencies\.\*\*\s*\n(.*?)\n\n', body, re.S)
    dtxt = dp.group(1) if dp else ''
    dep = re.search(r'Depends on (.*?)\. Feeds', dtxt)
    fed = re.search(r'Feeds (.*?)(?: with | \[t\+1\]|\.)', dtxt)
    r['depends'] = re.findall(r'`([a-z0-9\-]+)` \(#(\d{3})\)', dep.group(1)) if dep else []
    r['feeds'] = re.findall(r'`([a-z0-9\-]+)` \(#(\d{3})\)', dtxt.split('Feeds', 1)[1]) if 'Feeds' in dtxt else []
    sd = re.search(r'\*\*SDG alignment\.\*\*\s*\n(.*?)(?: In Tunisia|\n\n)', body, re.S)
    r['sdg'] = re.findall(r'SDG (\d+) · target ([\d\.]+)', sd.group(1)) if sd else []
    rk = re.search(r'\*\*Risks specific to this service\.\*\*\s*\n(.*?)\n\n', body, re.S)
    r['risks_en'] = [clean(x[2:]) for x in rk.group(1).split('\n') if x.startswith('- ')] if rk else []
    mb = re.search(r'The governing boundary is that (.*?)\. These are staged', ' '.join(r['long_en']))
    r['boundary_en'] = clean(mb.group(1)) if mb else ''
    r['md'] = svc_md.get(sid, {})
    out[sid] = r

json.dump(out, open(ROOT/'tools/gen/_parsed_services.json', 'w'), ensure_ascii=False, indent=1)
print(len(out), 'services parsed')
bad = [k for k, v in out.items() if not v.get('fields') or not v['deliverables'] or not v['boundary_en'] or not v['md'] or not v['sdg']]
print('incomplete:', bad[:30], len(bad))
print(json.dumps(out['001'], ensure_ascii=False, indent=1)[:2800])
