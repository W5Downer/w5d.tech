from cc import *
AS_OF = '2026-11-15'
ST = {'live': ('Live', 'En service', 'قائم'), 'building': ('Building', 'En construction', 'قيد البناء'),
      'concept': ('Concept', 'Concept', 'مفهوم'), 'draft': ('Draft', 'Brouillon', 'مسودة')}
def stl(s): e, f, a = ST[s]; return L(e, f, a)

P = []
def proj(slug, name, tagline, icon, status, outcome, services, evidence, timeline, name_ar=None, name_fr=None, extra=None, needs=None):
    P.append(dict(slug=slug, name=name, tagline=tagline, icon=icon, status=status, outcome=outcome, services=services, evidence=evidence, timeline=timeline, name_ar=name_ar, name_fr=name_fr, extra=extra or {}, needs=needs))

proj('2030b', '2030B', 'The strategic horizon: ten projects for the whole human.', 'sunrise', 'building',
 ['2030B is the longer strategic frame around W5D. The programme planning horizon is the current hiring window; 2030B is where the ten human-development projects (Be Smarter to Be Grateful) are meant to converge.',
  'It is labelled as building, not delivered. Each of the ten projects has its own page, its own status and its own date.'],
 ['project-management' if False else 'science-policy', 'education-culture', 'cultural-policy'], 'Site self-label "2030B Building"; ten project indexes on file.',
 [('2026-11-15', 'Ten project indexes completed', 'The Arabic indexes for all ten projects are on file.'), ('2030', 'Horizon', 'Convergence target, not a delivery promise.')])
proj('meta-x-academy', 'MetaX Academy', 'The academy layer that forms capability for the services.', 'graduation-cap', 'live',
 ['MetaX Academy runs the programmes where capability is formed: task sets, review queues and certificates that precede any delivery.',
  'It is one of five programmes on the public preview, each dated. It is the layer a graduate meets first.'],
 ['stem-education', 'education-culture', 'construction-training', 'digital-storytelling', 'science-comm'], 'Public preview lists MetaX Academy as live; nine MetaX pillars are registered.',
 [('2026-11-15', 'Live', 'Programme listed as live on the preview.'), ('2026-12-31', 'Programme clock end', 'Pricing and hiring window closes.')], needs='Course authors, reviewers, pilot cohorts.')
proj('kernid', 'KernID', 'Identity and credential layer, closest to the Dyon account system.', 'fingerprint', 'concept',
 ['KernID is a concept: an identity and credential layer so that each contributor, reviewer and certificate can be traced to one accountable person.',
  'It is the nearest relative of the Dyon-linked accounts that the platform uses today. Nothing here is live yet; the page exists so the concept is dated and inspectable.'],
 ['digital-identity', 'data-protection', 'cybersecurity'], 'Listed as a concept with a dated status.',
 [('2026-11-15', 'Concept registered', 'Dated entry in the project register.')], needs='Security reviewers, privacy counsel, identity engineers.')
proj('selfai', 'SelfAI', 'A meta-solution for self-training agents. Concept.', 'bot', 'concept',
 ['SelfAI is listed as a concept: a meta-solution for agents that train themselves under human supervision. An external research framework with a similar name exists; W5D does not claim it.',
  'Any deployment would need a defined use case, a baseline comparison and human escalation, as the Technology macro requires.'],
 ['ai-ml', 'platform-ai-governance', 'data-engineering'], 'Listed as a concept; not yet mapped to a specific service.',
 [('2026-11-15', 'Concept registered', 'Not yet mapped to a service.')], needs='ML engineers, evaluation leads.')
proj('ontology-department', 'Ontology Department', 'The organisational home of the five macros and 130 services.', 'network', 'live',
 ['The Ontology Department maintains the vocabulary: five macro works, 130 services, six layers, and the rules that connect them.',
  'Every service declares at least one input provider from another macro and one downstream consumer, which forces cross-domain coordination.'],
 ['research-data', 'official-stats', 'archival-science', 'cultural-data'], 'Listed as live on the preview; 16 departments registered.',
 [('2026-11-15', 'Live', '{{ counts.services }} services registered.')], needs='Ontology editors, domain reviewers.')
proj('kun-aalam', 'Be More Knowing', 'The eleventh project: an Arabic-language knowledge programme. Draft.', 'book-open', 'draft',
 ['Be More Knowing (كن أعلم) appears in the project registry as a draft. It is likely an Arabic-language knowledge and capability programme, but it is not yet specified.',
  'We list it so that nothing in the register is hidden, and we say plainly that it is a draft.'],
 ['stem-education', 'science-comm', 'library-modernisation'], 'Listed in the registry; no specification published.',
 [('2026-11-15', 'Draft registered', 'No specification yet.')], name_ar='كن أعلم', name_fr='Sois plus savant')
proj('webbooks', 'WebBooks', 'A publication layer for books that are read on the web.', 'book-marked', 'concept',
 ['WebBooks is listed next to the project register. It may connect to the Publishing and WebBooks service (#009) or to a broader publication layer.',
  'Status: concept. The reading template already exists in the site design (read-webbook).'],
 ['publishing', 'digital-storytelling', 'library-modernisation'], 'Listed alongside the register; service #009 exists.',
 [('2026-11-15', 'Concept registered', 'Linked to service #009.')], needs='Editors, typesetters, rights reviewers.')
proj('studio', 'Studio', 'Static-first sites, built to be checked.', 'layout-template', 'live',
 ['Studio applies the discipline of this very site to client work: static-first pages, no blocking loaders, no third-party requests, every figure dated.',
  'It is a service-delivery vehicle for partners who want to publish with the same evidence standard.'],
 ['software-eng', 'platform-eng', 'cloud-infra', 'cybersecurity'], 'Listed as live on the preview.',
 [('2026-11-15', 'Live', 'Client work uses the same standard.')], needs='Front-end engineers, accessibility reviewers.')
proj('macrolifetech-l2', 'MacroLifeTech L2 programmes', 'The five macro works presented as five programmes.', 'layers', 'live',
 ['The five macro works are presented as five L2 programmes above the services. The register shows five programmes, each dated.',
  'They are the programme layer an investor funds when they take a part.'],
 ['regulatory-impact', 'municipal-capacity' if False else 'municipal-capacity'.replace('municipal-capacity', 'official-stats'), 'research-data'], 'Five programmes on the public preview.',
 [('2026-11-15', 'Five programmes', 'Cultures, Built, Sciences, Governance, Technology.')])
proj('metax-pillars', 'MetaX pillars', 'Nine cross-cutting pillars of the organisation.', 'columns-3', 'live',
 ['Nine pillars are listed on the preview as cross-cutting organisational and ontological dimensions.',
  'They are documentation and governance structure, not a product.'],
 ['philosophy-ethics', 'cultural-policy', 'science-policy'], 'Nine pillars listed.',
 [('2026-11-15', 'Nine pillars listed', 'Register entry.')])
proj('developers-tunisia', 'Developers (Tunisia)', 'The real delivery capacity today: {{ experts.developers_today }} developers.', 'code', 'live',
 ['{{ experts.developers_today }} developers deliver in Tunisia today. This is the real footprint, distinct from the design ceiling of {{ d.experts_target }} expert seats.',
  'Every part sold funds the path from this number toward the ceiling; it does not claim the ceiling is reached.'],
 ['software-eng', 'cybersecurity', 'ai-ml', 'data-engineering', 'cloud-infra'], 'Dated developer count on the preview.',
 [('2026-11-15', '{{ experts.developers_today }} developers', 'Dated count.')], needs='Senior engineers who can lead teams of about {{ experts.team_size }}.')
proj('departments', 'Departments registry', '{{ experts.departments }} departments around the ontology.', 'building', 'live',
 ['{{ experts.departments }} departments form the administrative structure around the ontology.',
  'Department leads are the first managers the programme recruits.'],
 ['municipal-capacity' if False else 'decentralisation', 'it-governance', 'official-stats'], 'Sixteen departments in the registry.',
 [('2026-11-15', '{{ experts.departments }} departments', 'Registry count.')], needs='Department coordinators.')
proj('dyon', 'Dyon identity-linked accounts', 'One accountable identity for every contributor, reviewer and certificate.', 'badge-check', 'building',
 ['Dyon links each account to a verified person so that every weekly report and every certificate has an accountable author.',
  'Account counts are design totals ({{ d.accounts_total }} accounts across experts, managers, leaders and the owner), not issued accounts.'],
 ['digital-identity', 'data-protection', 'cybersecurity'], 'Described in the platform specification; account issuance has not started.',
 [('2026-11-15', 'Specified', 'Not yet issued at scale.')], needs='Privacy and security reviewers.')
proj('weekly-work-engine', 'Weekly work engine', 'Directives, reports, approvals, lock, rollup and archive, every week.', 'calendar-clock', 'building',
 ['The engine runs a weekly cycle: directives issued, reports submitted by Friday 12:00, approvals, a lock, a rollup and an archive.',
  'The {{ quality.sampling_percent }}% random audit rechecks source evidence; it does not replace manager review.'],
 ['official-stats', 'research-data', 'it-governance'], 'Implemented in the platform scripts; no production cohort yet.',
 [('2026-11-15', 'Implemented in code', 'Rollup and archive scripts exist.')])
proj('admin-dashboard', 'W5D Admin Dashboard', 'The owner portal, licensed at {{ money.license_hour }} {{ money.currency }} per hour of work.', 'layout-dashboard', 'building',
 ['The owner portal brings together 28 menus under eight roles: command center, pricing, investors, managers, subscriptions and the inbox.',
  'It is licensed at {{ money.license_hour }} {{ money.currency }} per hour of work. The promotional offer is valid before {{ dates.offer_year }}.'],
 ['software-eng', 'platform-eng', 'it-governance'], 'First menus live in this build: command center, pricing, subscriptions, managers, inbox.',
 [('2026-11-15', 'First menus live', 'Others show a read-only preview.')], needs='Product engineers, UX reviewers.')

BE = [
 ('be-smarter', 'Be Smarter', 'كن أذكى', 'Sois plus intelligent', 'brain', 'CTC', 'The mind', 'Human minds', 'Cognitive training credit', ['ai-ml', 'stem-education', 'math-modelling']),
 ('be-honester', 'Be Honester', 'كن أصدق', 'Sois plus honnête', 'shield-check', 'TIC', 'The conscience', 'Human consciences', 'Trust and integrity credit', ['anti-corruption', 'data-protection', 'digital-identity']),
 ('be-healthier', 'Be Healthier', 'كن أصح', 'Sois en meilleure santé', 'heart-pulse', 'VTC', 'The body', 'Human bodies', 'Vital training credit', ['public-health', 'clinical-research', 'food-safety']),
 ('be-creater', 'Be Creater', 'كن أبدع', 'Sois plus créatif', 'palette', 'INC', 'The imagination', 'Human imagination', 'Innovation credit', ['arts-economy', 'digital-storytelling', 'publishing']),
 ('be-kinder', 'Be Kinder', 'كن أرحم', 'Sois plus bienveillant', 'hand-heart', 'SCC', 'The spirit', 'Human spirits', 'Community compassion credit', ['social-protection', 'interfaith', 'oral-history']),
 ('be-braver', 'Be Braver', 'كن أشجع', 'Sois plus courageux', 'flame', 'WPC', 'Resolve', 'Human resolve', 'Courage credit', ['crisis-governance', 'disaster-construction', 'security-governance']),
 ('be-wiser', 'Be Wiser', 'كن أحكم', 'Sois plus sage', 'lightbulb', 'WDC', 'Wisdom', 'Human insight', 'Wisdom credit', ['philosophy-ethics', 'science-policy', 'history-science']),
 ('be-fairer', 'Be Fairer', 'كن أعدل', 'Sois plus juste', 'scale', 'JEC', 'Justice', 'Human scales', 'Justice credit', ['rule-of-law', 'legal-drafting', 'regulatory-impact']),
 ('be-freer', 'Be Freer', 'كن أحرر', 'Sois plus libre', 'bird', 'FLC', 'Free will', 'Free will', 'Freedom credit', ['labour-governance', 'platform-ai-governance', 'digital-governance']),
 ('be-grateful', 'Be Grateful', 'كن أشكر', 'Sois plus reconnaissant', 'sparkles', 'GRC', 'Grateful hearts', 'Grateful hearts', 'Gratitude credit', ['cultural-impact', 'heritage-conservation', 'education-culture']),
]
for slug, name, nar, nfr, ic, code, dim, tool, cur, sv in BE:
    proj(slug, name, 'A 2030B project for the human dimension: ' + dim.lower() + '. Concept.', ic, 'concept',
      [name + ' is one of the ten 2030B projects. Its human dimension is ' + dim.lower() + '; its tool is "' + tool + '"; its credit unit is ' + code + ' (' + cur + ').',
       'The Arabic index for this project is on file with three books: the guide for the person, the person and the machine, and the path from a skilled person to a self-training agent. The project is a concept: no product is live.'],
      sv, 'Arabic project index on file (completed ' + AS_OF + ').',
      [(AS_OF, 'Index completed', 'Three-book index on file.'), ('2030', 'Horizon', 'Target frame, not a delivery promise.')],
      name_ar=nar, name_fr=nfr, extra={'code': code, 'dimension': dim, 'parent': '2030b'})

assert len(P) == 25, len(P)
svc_ok = set(os.path.basename(f)[:-5] for f in __import__('glob').glob(C + '/services/*.json'))
for i, p in enumerate(P, 1):
    sv = [s for s in p['services'] if s in svc_ok]
    bad = [s for s in p['services'] if s not in svc_ok]
    if bad: print('WARN', p['slug'], 'unknown services', bad)
    name = {'en': p['name']}
    if p['name_fr']: name['fr'] = p['name_fr']
    if p['name_ar']: name['ar'] = p['name_ar']
    obj = {'id': i, 'order': i, 'icon': p['icon'], 'status': p['status'], 'status_label': stl(p['status']), 'status_date': AS_OF,
      'name': name, 'tagline': L(p['tagline']), 'outcome': [L(x) for x in p['outcome']], 'evidence': L(p['evidence']),
      'services': sv, 'timeline': [{'when': L(w), 'title': L(t), 'body': L(b)} for w, t, b in p['timeline']],
      'gradient': ['cultures', 'built', 'sciences', 'governance', 'technology'][i % 5]}
    if p['needs']: obj['needs'] = L(p['needs'])
    obj.update(p['extra'])
    write('projects', p['slug'], obj)
print('projects ok')
