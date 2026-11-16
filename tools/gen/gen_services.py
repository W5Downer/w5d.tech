from cc import *
import re
D = json.load(open(os.path.join(os.path.dirname(__file__), '_parsed_services.json')))
byid = {v['id']: v for v in D.values()}
def fix(s):
    s = s.replace('130,000', '{{ d.experts_target }}').replace('130 000', '{{ d.experts_target }}')
    return s
n = 0
for k, s in sorted(D.items()):
    md = s['md']
    obj = {
      'id': s['id'], 'macro': md['macro'], 'placement': s['placement'].split(' (register')[0],
      'placement_note': ('Virtual core' if 'Virtual core' in s['placement'] else ('Real instantiation' if 'Real instantiation' in s['placement'] else '')),
      'title': L(s['title_en']),
      'short': {'en': fix(s['short_en']), 'fr': s['short_fr'], 'ar': s['short_ar']},
      'abstract': L(fix(md['long'])),
      'long': [L(fix(p)) for p in s['long_en']],
      'fields': L(s['fields']), 'diploma': s['diploma'], 'cohort': s['cohort'], 'region': L(s['region']),
      'deliverables': [{'name': L(d['name']), 'unit': L(d['unit']), 'target': d['target']} for d in s['deliverables']],
      'evidence': L(fix(s['evidence_en'])), 'kpi': L(fix(s['kpi_en'])), 'boundary': L(s['boundary_en']),
      'depends': [{'slug': a, 'id': int(b)} for a, b in s['depends']],
      'feeds': [{'slug': a, 'id': int(b)} for a, b in s['feeds']],
      'sdg': [{'goal': g, 'target': t.rstrip('.')} for g, t in s['sdg']],
      'risks': [L(r) for r in s['risks_en']],
    }
    write('services', s['slug'], obj); n += 1
print(n, 'services written')
