from cc import *

# ---------------- NAV ----------------
def nl(icon, label, desc, href): return {'icon': icon, 'label': L(label), 'desc': L(desc), 'href': href}
nav_main = {'items': [
 {'label': L('Macro works'), 'active_prefix': '/macro-works', 'groups': [
   {'heading': L('Five macro works'), 'each': 'macros'},
   {'heading': L('Explore the ontology'), 'items': [
     nl('list-checks', '{{ counts.services }} services', 'Every service, its deliverables and limits', '/services/'),
     nl('folder-kanban', '{{ counts.projects }} projects', 'What is live, what is a concept', '/projects/'),
     nl('network', 'Ontology', 'Six layers, one vocabulary', '/ontology/')]}],
  'promo': {'tag': L('Design ceiling'), 'title': L('{{ d.experts_target }} expert seats'), 'body': L('A design equation, not a headcount. Read how it is built.'), 'href': '/trust/statement/', 'gradient': 'technology'}},
 {'label': L('Invest'), 'active_prefix': '/invest', 'groups': [
   {'heading': L('Take a part'), 'items': [
     nl('coins', 'Invest today', 'Buy a part now: 3 steps, demo checkout', '/checkout/'),
     nl('calendar-check', 'Daily subscription', 'At least one part, every day', '/subscription/'),
     nl('trending-up', 'Progressive price', 'The published curve, live', '/pricing/'),
     nl('briefcase', 'Investor dossier', 'What a part funds, and what it does not', '/investors/')]},
   {'heading': L('Verify before you commit'), 'items': [
     nl('shield-check', 'Declared limits', 'No job, no return, no promise', '/trust/statement/'),
     nl('activity', 'Status', 'Dated, sourced, auditable', '/status/'),
     nl('file-search', '5% sampling', 'How quality is checked', '/trust/sampling/')]}],
  'promo': {'tag': L('Live price'), 'title': L('From {{ price.day }} {{ money.currency }} per part per day'), 'body': L('Rises with programme progress and time. Capped at {{ pricing.cap }}× base.'), 'href': '/pricing/', 'gradient': 'peak'}},
 {'label': L('Join'), 'active_prefix': '/join', 'groups': [
   {'heading': L('As an expert'), 'items': [
     nl('user-check', 'Check eligibility', 'Thirty seconds, no account', '/join/eligibility/'),
     nl('user-plus', 'Register', 'Open a Dyon-linked applicant file', '/app/register'),
     nl('route', 'How it works', 'From diploma to weekly delivery', '/how-it-works/'),
     nl('map-pin', 'Tunisia hub', '{{ counts.governorates }} governorates', '/countries/tunisia/')]},
   {'heading': L('As a leader'), 'items': [
     nl('users', 'Become a manager', 'Lead about {{ experts.team_size }} experts', '/careers/managers/'),
     nl('briefcase-business', 'Careers', 'Service leaders and managers', '/careers/'),
     nl('handshake', 'Partners and clients', 'Commission a delivery', '/partners/')]}],
  'promo': {'tag': L('Hiring'), 'title': L('{{ d.managers_target }} manager seats by design'), 'body': L('We are recruiting expert team managers first.'), 'href': '/careers/managers/', 'gradient': 'governance'}},
 {'label': L('Company'), 'active_prefix': '/about', 'groups': [
   {'heading': L('W5D'), 'items': [
     nl('info', 'About', 'Who we are and what we will not claim', '/about/'),
     nl('circle-help', 'FAQ', 'Straight answers', '/faq/'),
     nl('mail', 'Contact', 'Write to the right desk', '/contact/')]},
   {'heading': L('Build with us'), 'items': [
     nl('code', 'Developers', 'JSON API and OpenAPI', '/developers/'),
     nl('bot', 'AI agents', 'Manifest, tools, llms.txt', '/agents/'),
     nl('newspaper', 'Newsletter', 'Dated updates only', '/newsletter/')]}]},
]}
write('nav', 'main', nav_main)

def fl(label, href): return {'label': L(label), 'href': href}
write('nav', 'footer', {'columns': [
 {'heading': L('Programme'), 'items': [fl('Macro works', '/macro-works/'), fl('130 services', '/services/'), fl('Projects', '/projects/'), fl('Ontology', '/ontology/'), fl('Tunisia hub', '/countries/tunisia/')]},
 {'heading': L('Invest'), 'items': [fl('Invest today', '/checkout/'), fl('Daily subscription', '/subscription/'), fl('Progressive price', '/pricing/'), fl('Investor dossier', '/investors/'), fl('Manage a subscription', '/subscription/manage/')]},
 {'heading': L('Join'), 'items': [fl('Check eligibility', '/join/eligibility/'), fl('Register', '/app/register'), fl('Become a manager', '/careers/managers/'), fl('How it works', '/how-it-works/'), fl('Partners', '/partners/')]},
 {'heading': L('Trust'), 'items': [fl('Declared limits', '/trust/statement/'), fl('5% sampling', '/trust/sampling/'), fl('Privacy', '/trust/privacy/'), fl('Terms', '/trust/terms/'), fl('Security', '/trust/security/'), fl('Corrections', '/trust/corrections/')]},
 {'heading': L('Build'), 'items': [fl('Developers', '/developers/'), fl('AI agents', '/agents/'), fl('llms.txt', '/llms.txt'), fl('Sitemap', '/sitemap/'), fl('Status', '/status/')]},
]})

# ---------------- MACROS ----------------
M = [
 dict(slug='cultures', id=1, icon='landmark', range='#001–#026', placement='Real 1.0',
  name='Cultures / Humanities', tagline='How societies remember, interpret, translate and transmit meaning.',
  q='How do societies remember, interpret, translate and transmit meaning?',
  fields='Archaeology, linguistics, museum studies, translation, film preservation, conservation, cultural economics',
  unit='Accepted capture packages, annotated recordings, translated words, restored footage minutes, catalogue records',
  boundary='Custodial permission and community consent precede publication; W5D never substitutes for statutory heritage authority.',
  body=['Cultures is the macro work with the most direct Tunisian anchor: a deep archive, a multilingual public and a graduate cohort in languages, history and heritage that the formal labour market absorbs slowly.',
        'It is explicitly not an IT-only demand map. Heritage conservation combines materials science, building safety and digital access; translation hubs feed science communication and digital governance.',
        'Its services feed Built Environment (heritage-built), Sciences (research-data, official-stats) and Governance (municipal-capacity).'],
  feeds='Built Environment, Sciences, Governance', why='Tunisia holds archives, manuscripts and living languages that are documented unevenly. Each capture package is a dated, reviewable unit of work with a named custodian.'),
 dict(slug='built', id=2, icon='building-2', range='#027–#052', placement='Real 1.0',
  name='Built Environment', tagline='How shelter, infrastructure and inhabited space are designed, delivered and maintained.',
  q='How are shelter, infrastructure and inhabited space designed, delivered and maintained?',
  fields='Civil engineering, architecture, urban planning, surveying, building services, hydraulics',
  unit='Design package reviews, asset inspections, leak survey kilometres, condition surveys, zoning layers',
  boundary='Affordability includes operating costs, not sale price alone; draft zoning stays advisory until the competent authority validates it.',
  body=['Built Environment carries the heaviest physical-infrastructure load and the most explicit dependence on the November 2023 CCDR findings, including SONEDE network losses of 34% in 2021.',
        'Engineers, architects and surveyors produce reviewable design, inspection and condition packages; a licensed professional on the client side signs what is binding.',
        'It feeds Sciences (environmental impact, climate modelling), Governance (municipal capacity, utility regulation) and Technology (BIM, construction-tech, renewable systems).'],
  feeds='Sciences, Governance, Technology', why='Water losses, ageing stock and rapid urbanisation create a standing backlog of inspection and design work that graduates in engineering can begin to document.'),
 dict(slug='sciences', id=3, icon='flask-conical', range='#053–#078', placement='Between 1.0',
  name='Sciences', tagline='How reliable knowledge is produced, measured, validated and turned into decision-grade evidence.',
  q='How is reliable knowledge produced, measured, validated and translated into decision-grade evidence?',
  fields='Epidemiology, analytical chemistry, agronomy, marine biology, materials engineering, metrology, biostatistics',
  unit='Accessioned samples, validated assay results, pipeline validation runs, calibration certificates, species observation records',
  boundary='Research analyses cannot become clinical diagnoses without authorised validation; laboratories publish method limits and chain of custody.',
  body=['The Between 1.0 placement reflects a laboratory-and-field hybrid: many outputs are data and method, while physical samples, field stations and accredited facilities are load-bearing.',
        'Sciences services feed every other macro, most critically Governance (health, climate, utility regulation) and Technology (biotech, medical devices).',
        'Every result ships with its method limit, its reviewer and its chain of custody.'],
  feeds='Governance, Technology, Built Environment', why='Tunisia graduates in science and agronomy are numerous; accredited methods and supervised sampling turn that capacity into evidence institutions can use.'),
 dict(slug='governance', id=4, icon='scale', range='#079–#104', placement='Between 1.0',
  name='Governance', tagline='How rules are made, decisions legitimised, public money allocated and accountability maintained.',
  q='How are rules made, legitimate decisions taken, public money allocated and accountability maintained?',
  fields='Law, public administration, economics, audit, social policy, statistics, international relations',
  unit='Regulatory option briefs, draft clause packages, integrity risk reviews, cash forecast reviews, MRV dataset reviews',
  boundary='Only authorised institutions adopt law, set tariffs, declare emergencies or award accreditation; W5D supports evidence and referrals, not statutory powers.',
  body=['Governance is the accountability macro. Its work is neither purely documentary nor purely physical: it needs legitimate authority, administrative records and implementation owners.',
        'It depends most on named beneficiaries: an advisory product without a public-service partner cannot be assumed to improve an institution.',
        'Its services reach all macros through the authorising environment.'],
  feeds='All macros via the authorising environment', why='Law, public administration and economics graduates can prepare option briefs and reviews that a competent authority may adopt, amend or reject.'),
 dict(slug='technology', id=5, icon='cpu', range='#105–#130', placement='Real 0.5 / Virtual 0.5',
  name='Technology', tagline='How deployable technical capabilities are built, secured, integrated and maintained.',
  q='How are deployable technical capabilities built, secured, integrated and maintained?',
  fields='Computer science, engineering, applied mathematics, cybersecurity, robotics, biomedical engineering',
  unit='Reviewed feature increments, alert investigations, validated pipeline changes, infrastructure change reviews, site device commissioning tests',
  boundary='Operators approve changes; model deployment requires a defined use case, baseline comparison and human escalation; testing requires written scope.',
  body=['Technology is internally split: #105–#117 are the Virtual core (primarily digital deliverables), while #118–#130 are Real instantiation (technology deployed into construction sites, water plants, farms, clinics, mines and cold chains).',
        'The Real 0.5 / Virtual 0.5 label is an organisational convention, not a measured fraction of hours.',
        'It is also where W5D already has delivery capacity: {{ experts.developers_today }} developers in Tunisia today.'],
  feeds='All macros via tooling and infrastructure', why='{{ experts.developers_today }} developers deliver in Tunisia today. The macro scales that proven capacity before the rest of the ontology.'),
]
for m in M:
    write('macros', m['slug'], {
      'id': m['id'], 'order': m['id'], 'icon': m['icon'], 'gradient': m['slug'], 'range': m['range'], 'placement': m['placement'],
      'name': L(m['name']), 'tagline': L(m['tagline']), 'question': L(m['q']), 'fields': L(m['fields']), 'unit': L(m['unit']),
      'boundary': L(m['boundary']), 'body': [L(x) for x in m['body']], 'feeds': L(m['feeds']), 'why': L(m['why'])})

# ---------------- LAYERS ----------------
LAYERS = [
 ('l0', 'L0', 0, 'Doctrine and dimensions', 'The five dimensions of W5D and the rule of Sidq: no page asserts more than its evidence supports.'),
 ('l1', 'L1', 1, 'Meta solutions', 'Cross-cutting tools used by every service: identity (KernID, Dyon), training (MetaX Academy), self-training agents (SelfAI), publishing (WebBooks).'),
 ('l2', 'L2', 2, 'Services', '{{ counts.services }} registered services, each with a deliverable unit, an evidence rule and a declared boundary.'),
 ('l3', 'L3', 3, 'Macro works', '{{ counts.macros }} macro works: the top-level demand map, {{ experts.services_per_macro }} services each.'),
 ('l4', 'L4', 4, 'Projects', '{{ counts.projects }} projects that consume services and publish dated status: live, building, concept or draft.'),
 ('l5', 'L5', 5, 'Programme and horizon', 'The 2030B horizon and the weekly work engine: reports, approvals, 5% sampling, rollups and archive.'),
]
for slug, code, o, name, body in LAYERS:
    write('layers', slug, {'code': code, 'order': o, 'name': L(name), 'body': L(body)})

# ---------------- SLIDERS ----------------
def slide(g, icon, eyebrow, title, lead, ctas, note=None):
    s = {'gradient': g, 'icon': icon, 'eyebrow': L(eyebrow), 'title': L(title), 'lead': L(lead), 'ctas': ctas}
    if note: s['note'] = L(note)
    return s
write('sliders', 'home', {'label': L('W5D highlights'), 'interval': 8000, 'slides': [
 slide('peak', 'coins', 'For investors', 'Take a part of W5D **today**. Then again tomorrow.',
   'One part is {{ price.day }} {{ money.currency }} per day at today\'s published price. Buy one part, or commit to at least one part every day until {{ dates.deadline }}.',
   [cta('Take a part today', '/checkout/', 'peak', True), cta('See the daily subscription', '/subscription/', 'ghost', False)],
   'A part is an investment in a path, not a job. No return is promised. Read the declared limits.'),
 slide('governance', 'users', 'For expert team managers', 'Lead about {{ experts.team_size }} experts. **Run** a service.',
   'W5D is recruiting managers first: people who can turn a rubric into weekly work for a team and sign a report they are willing to defend.',
   [cta('Apply as a manager', '/careers/managers/', 'peak', True), cta('See the roles', '/careers/', 'ghost', False)],
   '{{ d.managers_target }} manager seats exist by design. Today the certified headcount is {{ d.experts_current }}.'),
 slide('technology', 'graduation-cap', 'For graduates', 'Your diploma can become a **service**.',
   'Check in thirty seconds which of the {{ counts.services }} services matches your field. Certification tasks come first; delivery follows; nothing is promised before evidence.',
   [cta('Check my eligibility', '/join/eligibility/', 'peak', True), cta('How it works', '/how-it-works/', 'ghost', False)]),
 slide('sciences', 'network', 'The ontology', '**{{ counts.macros }} macro works.** {{ counts.services }} services. One vocabulary.',
   'Cultures, Built Environment, Sciences, Governance and Technology, {{ experts.services_per_macro }} services each, every one with a deliverable unit and a declared boundary.',
   [cta('Explore the macro works', '/macro-works/', 'peak', True), cta('Read the ontology', '/ontology/', 'ghost', False)]),
 slide('cultures', 'calendar-check', 'Daily subscription', 'At least one part, **every day**.',
   'Choose parts per day, a start date and a number of days. See the full daily schedule before you confirm. Pause or cancel with your management key.',
   [cta('Start a daily subscription', '/subscription/', 'peak', True), cta('Open the price chart', '/pricing/', 'ghost', False)],
   'Demo mode: no payment is taken until the payment provider is connected.'),
 slide('calm', 'scale', 'What we do not promise', 'No job. No return. **No design ceiling mistaken for a headcount.**',
   '"{{ d.experts_target }} experts" is {{ counts.macros }} × {{ experts.services_per_macro }} × {{ experts.per_service }}: a design equation. Today: {{ experts.developers_today }} developers deliver; {{ d.experts_current }} experts are certified.',
   [cta('Read the declared limits', '/trust/statement/', 'peak', True), cta('See the status page', '/status/', 'ghost', False)]),
]})

MS = {
 'cultures': ('landmark', 'Cultures / Humanities', 'Heritage, language, memory', 'Capture what Tunisia knows before it is lost.', 'Twenty-six services turn graduates in history, languages and heritage into reviewable capture, translation and cataloguing work, with custodial consent first.'),
 'built': ('building-2', 'Built Environment', 'Shelter and infrastructure', 'Inspect, design and rehabilitate what people live in.', 'Twenty-six services for engineers and architects: condition surveys, design reviews, water-loss surveys and retrofit packages, signed off by a licensed professional.'),
 'sciences': ('flask-conical', 'Sciences', 'Evidence you can cite', 'Measure carefully. Publish the method limit.', 'Twenty-six services for scientists: accessioned samples, validated assays, calibration and field records, each with its chain of custody.'),
 'governance': ('scale', 'Governance', 'Rules and accountability', 'Prepare the options. Let the authority decide.', 'Twenty-six services for lawyers, economists and administrators: option briefs, clause packages, integrity reviews, with named public beneficiaries.'),
 'technology': ('cpu', 'Technology', 'Build, secure, deploy', 'From {{ experts.developers_today }} developers today to a deliverable-based programme.', 'Twenty-six services from software and security to deployed devices in water plants, farms and clinics, with human approval of every change.'),
}
for slug, (ic, name, eb, title, lead) in MS.items():
    write('sliders', 'macro-' + slug, {'label': L(name), 'interval': 9000, 'height': '34rem', 'slides': [
      slide(slug, ic, eb, title, lead, [cta('Find my service here', '#mw-catalog', 'peak', True), cta('Check my eligibility', '/join/eligibility/', 'ghost', False)]),
      slide(slug, 'users', name + ' · leaders', 'Lead a **{{ experts.team_size }}-expert** team in ' + name + '.',
            '{{ experts.managers_per_service }} managers per service by design. Managers translate a service leader\'s rubric into weekly assignments and sign the report.',
            [cta('Apply as a manager', '/careers/managers/', 'peak', True), cta('Open careers', '/careers/', 'ghost', False)]),
      slide(slug, 'coins', name + ' · investors', 'Fund the path into **' + name + '**.',
            'A part funds certification and delivery capacity across the programme at a published, capped price. No return is promised.',
            [cta('Take a part today', '/checkout/?macro=' + slug, 'peak', True), cta('See the price', '/pricing/', 'ghost', False)],
            'Demo mode: no payment is taken.'),
    ]})

write('sliders', 'projects', {'label': L('Projects'), 'interval': 8500, 'height': '32rem', 'slides': [
 slide('technology', 'folder-kanban', 'Project register', '**{{ counts.projects }} projects.** Each one dated, each one honest.',
   'Live, building, concept or draft: the status is on the page, with its date and its evidence. Nothing is presented as more finished than it is.',
   [cta('Open the register', '#prj-index', 'peak', True), cta('Read the limits', '/trust/statement/', 'ghost', False)]),
 slide('cultures', 'graduation-cap', 'MetaX Academy', 'The academy layer behind the **{{ counts.services }} services**.',
   'MetaX Academy forms the capability that the services consume: task sets, review queues and certificates.',
   [cta('Open MetaX Academy', '/projects/meta-x-academy/', 'peak', True), cta('How certification works', '/how-it-works/', 'ghost', False)]),
 slide('governance', 'heart-handshake', '2030B', 'Ten projects for the **whole human**.',
   'Be Smarter, Honester, Healthier, Creater, Kinder, Braver, Wiser, Fairer, Freer, Grateful: ten tools, one horizon.',
   [cta('See 2030B', '/projects/2030b/', 'peak', True), cta('All projects', '/projects/', 'ghost', False)]),
 slide('peak', 'coins', 'Contribute', 'Contribute to a project. **Today.**',
   'Join as an expert, become a client or take a part. Every project page shows the services it needs and the gaps that remain.',
   [cta('Take a part today', '/checkout/', 'peak', True), cta('Join as an expert', '/join/eligibility/', 'ghost', False)]),
]})
print('gen1 ok')
