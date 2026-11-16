from cc import *

def page(slug, title, desc, sections, robots=None):
    o = {'title': L(title), 'description': L(desc), 'sections': sections}
    if robots: o['robots'] = robots
    write('pages', slug, o)

def hero(id, title, lead, ctas, eyebrow=None, gradient='peak', variant='classic', badges=None, note=None, icon=None):
    d = {'type': 'hero', 'id': id, 'variant': variant, 'gradient': gradient, 'title': L(title), 'lead': L(lead), 'ctas': ctas}
    if eyebrow: d['eyebrow'] = L(eyebrow)
    if badges: d['badges'] = [{'icon': i, 'label': L(l)} for i, l in badges]
    if note: d['note'] = L(note)
    if icon: d['icon'] = icon
    return d
def fcta(id, title, body, ctas, gradient='peak', note=None):
    d = {'type': 'cta', 'id': id, 'gradient': gradient, 'title': L(title), 'body': L(body), 'ctas': ctas}
    if note: d['note'] = L(note)
    return d
def cards(id, title, items, lead=None, tier='i', focus=2, cols=3, eyebrow=None, ctas=None):
    d = {'type': 'cards', 'id': id, 'tier': tier, 'focus': focus, 'cols': cols, 'title': L(title),
         'items': [{**{k: (L(v) if k in ('title', 'body', 'cta', 'tag') else v) for k, v in it.items()}} for it in items]}
    if lead: d['lead'] = L(lead)
    if eyebrow: d['eyebrow'] = L(eyebrow)
    if ctas: d['ctas'] = ctas
    return d
def prose(id, title, paras, tier='e', focus=3, ctas=None, lead=None, lst=None, eyebrow=None):
    d = {'type': 'prose', 'id': id, 'tier': tier, 'focus': focus, 'title': L(title), 'paragraphs': [L(p) for p in paras]}
    if lead: d['lead'] = L(lead)
    if lst: d['list'] = [L(x) for x in lst]
    if ctas: d['ctas'] = ctas
    if eyebrow: d['eyebrow'] = L(eyebrow)
    return d
def faq(id, title, qa, tier='e', ctas=None):
    d = {'type': 'faq', 'id': id, 'tier': tier, 'focus': 2, 'title': L(title), 'items': [{'q': L(q), 'a': L(a)} for q, a in qa]}
    if ctas: d['ctas'] = ctas
    return d
def steps(id, title, items, tier='i', focus=3, lead=None, ctas=None, type='steps'):
    d = {'type': type, 'id': id, 'tier': tier, 'focus': focus, 'title': L(title), 'items': [{'title': L(a), 'body': L(b), **({'meta': L(c)} if c else {})} for a, b, c in items]}
    if lead: d['lead'] = L(lead)
    if ctas: d['ctas'] = ctas
    return d
def kpis(id, title, items, tier='e', focus=2, lead=None):
    d = {'type': 'kpis', 'id': id, 'tier': tier, 'focus': focus, 'title': L(title) if title else None,
         'items': [{'value': v, 'label': L(l), **({'note': L(n)} if n else {}), **({'suffix': s} if s else {}), **({'dec': dc} if dc else {})} for v, l, n, s, dc in items]}
    if not title: del d['title']
    if lead: d['lead'] = L(lead)
    return d
def notice(id='notice', items=None, title=None):
    d = {'type': 'notice', 'id': id, 'tier': 'i', 'focus': 1}
    if items: d['items'] = [L(x) for x in items]
    if title: d['title'] = L(title)
    return d
def table(id, title, cols, rows, tier='i', focus=3, note=None, lead=None, ctas=None):
    d = {'type': 'table', 'id': id, 'tier': tier, 'focus': focus, 'title': L(title), 'columns': [L(c) for c in cols], 'rows': [[L(c) for c in r] for r in rows]}
    if note: d['note'] = L(note)
    if lead: d['lead'] = L(lead)
    if ctas: d['ctas'] = ctas
    return d
def defs(id, title, items, tier='x', focus=1, lead=None):
    d = {'type': 'definitions', 'id': id, 'tier': tier, 'focus': focus, 'title': L(title), 'items': [{'term': L(a), 'def': L(b)} for a, b in items]}
    if lead: d['lead'] = L(lead)
    return d
def form(id, action, title, fields, submit, lead=None, note=None):
    d = {'type': 'form', 'id': id, 'tier': 'e', 'focus': 4, 'action': action, 'title': L(title), 'submit': L(submit), 'fields': fields}
    if lead: d['lead'] = L(lead)
    if note: d['note'] = L(note)
    return d
def fld(name, label, type='text', required=False, options=None, hint=None, span=1, rows=None):
    d = {'name': name, 'label': L(label), 'type': type, 'required': required, 'span': span}
    if options: d['options'] = {k: L(v) for k, v in options.items()}
    if hint: d['hint'] = L(hint)
    if rows: d['rows'] = rows
    return d

INV = cta('Take a part today', '/checkout/', 'peak', True)
SUB = cta('Daily subscription', '/subscription/', 'ghost', False)
LIM = cta('Read the declared limits', '/trust/statement/', 'link', True)

# ===== HOME =====
page('home', 'W5D — take a part today, lead an expert team',
 'W5D is a company and a working ontology: five macro works, {{ counts.services }} services. Investors take a part every day; expert team managers are recruited first.',
 [
  {'type': 'slider', 'id': 'home-hero', 'source': 'home'},
  kpis('home-trust-strip', None, [
     ('{{ counts.macros }}', 'macro works', None, None, 0), ('{{ counts.services }}', 'registered services', None, None, 0),
     ('{{ experts.developers_today }}', 'developers delivering in Tunisia today', 'Dated {{ dates.as_of }}', None, 0),
     ('{{ d.experts_current }}', 'experts certified today', 'Design ceiling: {{ d.experts_target }}', None, 0),
     ('{{ price.day }}', '{{ money.currency }} per part per day, today', 'Cap {{ pricing.cap }}× base', None, 2)]),
  {'type': 'investor-strip', 'id': 'home-investors', 'tier': 'e', 'focus': 4, 'eyebrow': L('Investors'), 'title': L('One part a day is a **position in the path**, not a promise.'),
   'lead': L('Parts fund certification and delivery capacity. The price is a public function of programme progress and time. You see the whole schedule before you confirm, and you can pause or stop.'),
   'ctas': [INV, SUB]},
  prose('home-problem', 'Graduates are many. Verified, supervised work is scarce.', [
     'The World Bank\'s 2022 overview compares 64,540 annual university graduates with 10,675 net additional high-end jobs a year. That gap is the reason the programme exists; it is not a claim about any single speciality.',
     'W5D does not sell a job. It builds a supervised path: certification tasks first, then weekly deliverables with a named reviewer, then a rollup anyone can inspect.',
     'The path is funded in parts, and led by managers who each answer for about {{ experts.team_size }} experts. That is why managers are recruited first.'],
     ctas=[cta('Is this me?', '#home-fit', 'link', True)]),
  cards('home-fit', 'Four doors, one standard of evidence', [
     {'icon': 'coins', 'title': 'I want to invest', 'body': 'Take one part today or commit to one every day. See the price function and the full schedule first.', 'href': '/checkout/', 'cta': 'Take a part'},
     {'icon': 'users', 'title': 'I can lead a team', 'body': 'Apply as an expert team manager: about {{ experts.team_size }} experts, a weekly rubric, a report you sign.', 'href': '/careers/managers/', 'cta': 'Apply as manager'},
     {'icon': 'graduation-cap', 'title': 'I hold a diploma', 'body': 'Check which of the {{ counts.services }} services fits your field in thirty seconds.', 'href': '/join/eligibility/', 'cta': 'Check eligibility'},
     {'icon': 'handshake', 'title': 'I need work delivered', 'body': 'Commission a scoped delivery from a service team, with a named beneficiary and an acceptance rule.', 'href': '/partners/', 'cta': 'Book a scoping call'}], tier='e', focus=3, cols=4,
     lead='Pick the door that matches you.'),
  steps('home-pipeline', 'From part to proof in four steps', [
     ('Parts are bought', 'Each part is priced by the published function and recorded with its date, price and progress.', 'Every day'),
     ('Experts are certified', 'Task sets L1 to L4 are reviewed by a competent reviewer before any delivery.', 'Weeks'),
     ('Teams deliver weekly', 'About {{ experts.team_size }} experts per manager; reports are due Friday 12:00.', 'Weekly'),
     ('Evidence is sampled', '{{ quality.sampling_percent }}% of reports are re-checked at random and the rollup is public.', 'Weekly')],
     lead='The pipeline is deliberately boring: every step leaves a dated record.', ctas=[cta('See the certification path', '/how-it-works/', 'link', True)], type='pipeline'),
  {'type': 'macro-cards', 'id': 'home-macros', 'tier': 'i', 'focus': 3, 'title': L('Five macro works, {{ experts.services_per_macro }} services each'), 'lead': L('The top-level demand map. Choose the one that matches your field or your thesis.'), 'ctas': [cta('Explore all macro works', '/macro-works/', 'link', True)]},
  fcta('home-mid-cta', 'Take your first part **today**.', 'It takes three steps. In demo mode no money moves; your pledge and your reference are recorded.', [INV, cta('See today\'s price', '/pricing/', 'ghost', False)], 'peak', 'A part is an investment in a path, not a job. No return is promised.'),
  {'type': 'gov-map', 'id': 'home-gov-map', 'tier': 'e', 'focus': 3, 'title': L('{{ counts.governorates }} governorates, one standard'), 'lead': L('Experts register from their governorate. Hubs, universities and coordinators are listed per region as they are confirmed.'), 'ctas': [cta('Open the Tunisia hub', '/countries/tunisia/', 'link', True)]},
  {'type': 'stack', 'id': 'home-stack', 'tier': 'x', 'focus': 1, 'title': L('The ontology in six layers'), 'lead': L('From doctrine (L0) to programme horizon (L5). Every service sits at L2 and every page says which layer it speaks about.'), 'ctas': [cta('See the ontology', '/ontology/', 'link', True)]},
  {'type': 'project-cards', 'id': 'home-projects', 'tier': 'i', 'focus': 2, 'title': L('Projects: live, building, concept, draft'), 'lead': L('Status and date are on every card.'),
   'only': ['meta-x-academy', 'ontology-department', 'studio', 'kernid', 'selfai', '2030b'], 'limit': 6, 'ctas': [cta('All {{ counts.projects }} projects', '/projects/', 'link', True)]},
  {'type': 'quote', 'id': 'home-founder', 'tier': 'e', 'focus': 2, 'gradient': 'warm', 'text': L('No page asserts more than its evidence supports.'), 'by': L('The rule of Sidq, W5D doctrine'), 'ctas': [cta('Read the declarations', '/about/', 'link', True)]},
  notice('home-limits'),
  faq('home-faq', 'Questions investors and managers ask first', [
     ('What exactly am I buying when I take a part?', 'A part is a dated contribution to the programme that funds certification and delivery capacity. It is not equity, a loan, a salary or a promise of return. The price function and your schedule are shown before you confirm.'),
     ('Why does the price rise?', 'The price per part rises with programme progress (p) and elapsed time (t), capped at {{ pricing.cap }}× the base. Earlier parts cost less. The formula is public on the pricing page.'),
     ('Can I stop a daily subscription?', 'Yes. Every subscription has a management key. Pause, resume or cancel at any time; future days are not charged after you cancel.'),
     ('Is the {{ d.experts_target }} figure real?', 'It is a design equation ({{ counts.macros }} × {{ experts.services_per_macro }} × {{ experts.per_service }}). Today {{ experts.developers_today }} developers deliver and {{ d.experts_current }} experts are certified.'),
     ('Why are managers recruited first?', 'Because a team of about {{ experts.team_size }} needs an accountable person before it needs more members. Managers set the pace and sign the report.')],
     ctas=[cta('Ask us', '/contact/', 'link', True)]),
  fcta('home-final', 'Start with one part. **Or lead a team.**', 'Investors take a part. Managers apply. Graduates check eligibility. Everyone sees the limits first.', [INV, cta('Apply as a manager', '/careers/managers/', 'ghost', False)], 'peak'),
  {'type': 'sticky-cta', 'id': 'sticky', 'href': '/checkout/', 'label': L('Take a part today')},
 ])

# ===== INVESTORS =====
page('investors', 'Investors — take a part every day',
 'The investor dossier: what a part funds, how the price is computed, how to commit to a daily part, and what W5D does not promise.',
 [
  hero('inv-hero', 'Take a part of W5D. **Every day.**', 'A part is {{ price.day }} {{ money.currency }} today. Commit to at least one part per day for as many days as you choose, and see the full schedule before you confirm.',
       [INV, SUB], eyebrow='Investor dossier', gradient='peak', variant='product', badges=[('shield-check', 'Public price function'), ('pause', 'Pause or cancel any time'), ('file-text', 'Dated, sourced figures')],
       note='Demo mode: no payment is taken. A part is an investment in a path, not a job. No return is promised.'),
  kpis('inv-numbers', 'The numbers behind a part', [
     ('{{ price.day }}', '{{ money.currency }} per part per day, today', 'Base {{ money.base_day }}, cap {{ pricing.cap }}×', None, 2),
     ('{{ money.salary_monthly }}', '{{ money.currency }} per expert post per month', 'Salary model per 30-day month', None, 0),
     ('{{ d.part_monthly }}', '{{ money.currency }} per part per month at base', '{{ money.base_day }} × 30', None, 0),
     ('{{ d.parts_per_post }}', 'parts at base to cover one post-month', 'Design arithmetic', None, 0),
     ('{{ d.payroll_monthly }}', '{{ money.currency }} per month at the design ceiling', '{{ d.experts_target }} × {{ money.salary_monthly }}', None, 0)],
     lead='These are design figures, computed from the same variables the pricing page uses.'),
  prose('inv-what', 'What a part funds', [
     'Parts fund the path from today\'s footprint ({{ experts.developers_today }} developers) toward the design ceiling: certification tasks, reviewer time, manager onboarding, tooling and the weekly work engine.',
     'Every part is recorded with its date, its unit price and the programme progress at that moment. Your daily subscription is a schedule of such records.',
     'Nothing in this programme is a guarantee of employment for anyone, or of financial return for you.'], tier='e', focus=3),
  {'type': 'price-chart', 'id': 'inv-price', 'tier': 'i', 'focus': 3, 'title': L('The price function, live'), 'lead': L('price = base × (1 + α·p^k) × (1 + β·t^m), capped. p is certified experts over the design target; t is the share of the programme clock elapsed.'), 'ctas': [cta('Open the pricing page', '/pricing/', 'link', True)]},
  steps('inv-how', 'Three steps to your first part', [
     ('Choose', 'Parts per day, start date, number of days, and optionally a macro work you want to follow.', '1 minute'),
     ('Review', 'See the unit price for each day, the total, and the declared limits. Nothing is charged in demo mode.', '1 minute'),
     ('Confirm', 'You receive a reference and a management key. Pause, resume or cancel from the manage page.', 'Instant')],
     lead='The wizard shows the live price before every decision.', ctas=[INV]),
  table('inv-compare', 'One part, two ways to buy', ['', 'Single purchase', 'Daily subscription'], [
     ['Commitment', 'One-off', 'At least one part per day for the days you choose'],
     ['Schedule', 'One date', 'Full day-by-day schedule shown up front'],
     ['Price', 'Price on the day', 'Forecast at today\'s progress, recomputed each day'],
     ['Control', 'None needed', 'Pause, resume, cancel with your key'],
     ['Best for', 'Testing the process', 'Backing the programme steadily']], tier='e', focus=3),
  defs('inv-terms', 'Terms in plain words', [
     ('Part', 'A dated unit of contribution priced by the published function.'),
     ('Progress (p)', 'Certified experts divided by the design target, from the daily rollup.'),
     ('Time (t)', 'Elapsed share of the programme clock from {{ dates.start }} to {{ dates.deadline }}.'),
     ('Cap', 'The price never exceeds {{ pricing.cap }}× base, whatever p and t are.'),
     ('Demo mode', 'Pledges are recorded, no payment is taken, no money moves.'),
     ('Management key', 'A secret token that lets you pause, resume or cancel a subscription.')]),
  notice('inv-limits', ['A part is an investment in a path, not a job. W5D does not promise employment.', 'No return, dividend or resale value is promised. The price function is public.', 'Experts certified today: {{ d.experts_current }}. The design ceiling is {{ d.experts_target }}.']),
  faq('inv-faq', 'Investor questions', [
     ('Is this a security or equity?', 'No. A part is a contribution to a published programme. Legal structuring for any future instrument would be published before it is offered.'),
     ('Who verifies the figures?', 'Progress comes from the daily rollup; the price function and its constants are in the public pricing page and the JSON API; every change to a constant is logged.'),
     ('Can I get the dossier by email?', 'Yes. Use the contact form and choose the investor desk, or write to {{ investor.contact }}.')],
     ctas=[cta('Contact the investor desk', '/contact/?desk=investors', 'link', True)]),
  fcta('inv-final', 'Take a part **today**.', 'Three steps, a full schedule, a reference you can verify.', [INV, SUB], 'peak', 'Demo mode: no payment is taken.'),
  {'type': 'sticky-cta', 'id': 'sticky', 'href': '/checkout/', 'label': L('Take a part today')},
 ])

# ===== PRICING =====
page('pricing', 'Progressive price — one public function',
 'The price per part rises with programme progress and time, capped at {{ pricing.cap }}× base. The curve, the constants and the grid are public.',
 [
  hero('pricing-hero', 'One price function. **Public.**', 'Today a part costs {{ price.day }} {{ money.currency }} per day or {{ price.week }} per week. It rises as the programme progresses and as time passes, and it never exceeds {{ pricing.cap }}× base.',
       [cta('See the progressive price', '#pricing-chart', 'peak', True), cta('Take a part', '/checkout/', 'ghost', False)], eyebrow='Pricing', gradient='technology'),
  {'type': 'price-chart', 'id': 'pricing-chart', 'tier': 'i', 'focus': 4, 'title': L('Live curve'), 'lead': L('Day and week price while p and t move together from 0 to 100%. The dot is today.'), 'ctas': [INV]},
  {'type': 'price-table', 'id': 'pricing-grid', 'tier': 'i', 'focus': 3, 'title': L('Price grid per part per day'), 'lead': L('Rows are progress p, columns are time t.')},
  defs('pricing-constants', 'Published constants', [
     ('Base per day', '{{ money.base_day }} {{ money.currency }}'), ('Base per week', '{{ money.base_week }} {{ money.currency }}'),
     ('α (progress weight)', '{{ pricing.alpha }}'), ('β (time weight)', '{{ pricing.beta }}'),
     ('k (progress exponent)', '{{ pricing.p_prog }}'), ('m (time exponent)', '{{ pricing.p_time }}'),
     ('Cap', '{{ pricing.cap }}× base'), ('Clock', '{{ dates.start }} to {{ dates.deadline }} (Africa/Tunis)')], lead='Changing any constant is a dated, logged event.'),
  prose('pricing-why', 'Why a rising price is fair, and what it is not', [
     'A rising price rewards earlier support: the first parts fund the hardest stage, when the programme has the least evidence. It is a declared curve, not a scarcity trick.',
     'It is also not a promise that the part will be worth more later. Parts carry no return, dividend or resale value.',
     'The same function is available as JSON at `/api/price.json`, so you can verify any quote independently.'], tier='e', focus=3, ctas=[LIM]),
  fcta('pricing-final', 'Lock in today\'s unit price **with a daily schedule.**', 'The schedule shows each day\'s forecast price. The price on the day is recomputed from the live function.', [cta('Start a daily subscription', '/subscription/', 'peak', True)], 'peak'),
 ])

# ===== SUBSCRIPTION =====
page('subscription', 'Daily subscription — at least one part every day',
 'Commit to at least one part per day. Choose parts, start date and number of days, review the schedule, confirm. Pause or cancel with your key.',
 [
  hero('sub-hero', 'At least one part, **every day.**', 'Backing a programme steadily beats a single large gesture. Choose a rhythm, see every day\'s forecast price, and keep control with a management key.',
       [cta('Build my schedule', '#sub-wizard', 'peak', True), cta('Manage an existing subscription', '/subscription/manage/', 'ghost', False)], eyebrow='Daily subscription', gradient='cultures',
       badges=[('calendar-check', 'Full schedule before you confirm'), ('pause', 'Pause or cancel'), ('lock', 'Management key')], note='Demo mode: no payment is taken until the provider is connected.'),
  {'type': 'wizard', 'id': 'sub-wizard', 'mode': 'subscription', 'tier': 'e', 'focus': 4, 'title': L('Build your daily schedule')},
  notice('sub-limits'),
  faq('sub-faq', 'Subscription questions', [
     ('What happens if the price changes during my schedule?', 'The schedule shows a forecast using today\'s progress and each day\'s time share. The price on each day is recomputed from the live function, and every charge records the unit price used.'),
     ('How do I cancel?', 'Open the manage page with your reference and management key. Cancelling stops all future days.'),
     ('Is anything charged now?', 'No. In demo mode no payment is taken. When a payment provider is connected, the payment terms will be shown before you confirm.')]),
 ])

# ===== CAREERS (managers first) =====
page('careers', 'Careers — expert team managers wanted',
 'W5D recruits expert team managers first: leaders of about {{ experts.team_size }} experts, who turn a service rubric into weekly work and sign the report.',
 [
  hero('car-hero', 'Lead an expert team. **Sign the report.**', 'We are recruiting managers first. A manager leads about {{ experts.team_size }} experts, turns a service leader\'s rubric into weekly assignments, and answers for the report.',
       [cta('Apply as a manager', '/careers/managers/', 'peak', True), cta('See the roles', '#car-roles', 'ghost', False)], eyebrow='Careers', gradient='governance',
       badges=[('users', 'About {{ experts.team_size }} experts per team'), ('calendar', 'Weekly cycle, Friday 12:00'), ('badge-check', 'Dyon-linked accountability')]),
  {'type': 'roster', 'id': 'car-roles', 'tier': 'i', 'focus': 3, 'title': L('Three levels of leadership'), 'items': [
     {'icon': 'crown', 'title': L('Service leader'), 'body': L('Sets the acceptance rubric for one of {{ counts.services }} services and reviews the managers\' reports.'), 'count': L('{{ d.leaders_target }} by design')},
     {'icon': 'users', 'title': L('Expert team manager'), 'body': L('Leads about {{ experts.team_size }} experts: weekly assignments, review, sign-off. The first role we recruit.'), 'count': L('{{ d.managers_target }} by design')},
     {'icon': 'user-check', 'title': L('Certified expert'), 'body': L('Completes task sets L1 to L4, then delivers weekly units with a named reviewer.'), 'count': L('{{ d.experts_current }} certified today')}]},
  steps('car-process', 'How the manager selection works', [
     ('Apply', 'A short form: experience, largest team led, a scenario answer and a profile link.', '10 minutes'),
     ('Score', 'The scenario is scored against a published rubric; the score is shown to the reviewer, not hidden from you.', 'Within 5 days'),
     ('Interview', 'A structured conversation about one real weekly report you would sign.', '45 minutes'),
     ('Pilot', 'A one-month pilot with a small team, reviewed before any larger responsibility.', '4 weeks')], lead='Selection is about judgement under load, not credentials alone.', ctas=[cta('Apply now', '/careers/managers/', 'link', True)]),
  prose('car-honest', 'What we promise, and what we do not', [
     'We promise a clear rubric, a named reviewer and a transparent score. We do not promise a salary for a seat that does not exist yet.',
     'Posts are created as certified headcount and funded capacity grow. Today {{ d.experts_current }} experts are certified and {{ experts.developers_today }} developers deliver.'], tier='e', focus=2, ctas=[LIM]),
  fcta('car-final', 'If you can lead ten people, **we want to read your answer.**', 'Applications are scored and answered with the score.', [cta('Apply as a manager', '/careers/managers/', 'peak', True)], 'governance'),
 ])

page('careers-managers', 'Apply as an expert team manager',
 'Application form for expert team managers: experience, team size, scenario answer. You receive a reference and a score.',
 [
  hero('mgr-hero', 'Apply as an **expert team manager.**', 'Tell us what you have led and how you would handle a hard week. You get a reference immediately and a reviewer answers with your score.',
       [cta('Open the form', '#mgr-form', 'peak', True)], eyebrow='Managers', gradient='governance', note='We do not promise a post. See the declared limits.'),
  form('mgr-form', '/careers/managers/', 'Your application', [
     fld('name', 'Full name', 'text', True), fld('email', 'Email', 'email', True), fld('phone', 'Phone', 'tel'),
     fld('region', 'Governorate', 'select', True, {'': 'Choose…'}),
     fld('macro', 'Macro work you can lead in', 'select', True, {'cultures': 'Cultures / Humanities', 'built': 'Built Environment', 'sciences': 'Sciences', 'governance': 'Governance', 'technology': 'Technology'}),
     fld('years', 'Years of experience', 'number', True), fld('team', 'Largest team led (people)', 'number', True),
     fld('profile', 'Professional profile URL', 'url'),
     fld('motivation', 'Why this role?', 'textarea', True, span=2, rows=4),
     fld('scenario', 'Scenario: on Friday 11:30 two of your ten experts have not submitted and one report looks copied. What do you do before 12:00, and what do you sign?', 'textarea', True, span=2, rows=6, hint='Scored on judgement, evidence and honesty, not on length.'),
     fld('accept', 'I have read the [declared limits](/trust/statement/) and understand that no post is promised.', 'checkbox', True, span=2)],
     'Send my application', lead='About 10 minutes.'),
 ])

# ===== JOIN / ELIGIBILITY / REGISTER =====
page('join', 'Join as an expert — check, register, certify',
 'Graduates: check eligibility, register, complete certification tasks, then deliver weekly units. Nothing is promised before evidence.',
 [
  hero('join-hero', 'Your diploma can become a **service.**', 'Check in thirty seconds which of the {{ counts.services }} services fits your field, then register. Certification comes first, delivery follows.',
       [cta('Check my eligibility', '/join/eligibility/', 'peak', True), cta('Register now', '/app/register', 'ghost', False)], eyebrow='Join', gradient='technology', note='A part is an investment in a path, not a job. W5D does not promise employment.'),
  steps('join-steps', 'The path', [
     ('Check', 'Diploma level and field give you a short list of services.', '30 seconds'),
     ('Register', 'Open an applicant file linked to one verified identity.', '5 minutes'),
     ('Certify', 'Complete task sets L1 to L4, reviewed by a competent reviewer.', 'Weeks'),
     ('Deliver', 'Join a team of about {{ experts.team_size }} and file weekly units.', 'Weekly')]),
  {'type': 'gov-map', 'id': 'join-gov', 'tier': 'e', 'focus': 3, 'title': L('Find your governorate'), 'ctas': [cta('Tunisia hub', '/countries/tunisia/', 'link', True)]},
  notice('join-limits'),
  fcta('join-final', 'Start with the **thirty-second check.**', 'No account needed.', [cta('Check my eligibility', '/join/eligibility/', 'peak', True)], 'peak'),
 ])
page('join-eligibility', 'Check my eligibility', 'Thirty-second eligibility check by diploma level and field.',
 [
  hero('elig-hero', 'Which service **fits you?**', 'Answer three questions. We suggest services; we do not promise a place.', [cta('Start', '#elig-form', 'peak', True)], eyebrow='Eligibility', gradient='technology'),
  form('elig-form', '/join/eligibility/', 'Three questions', [
     fld('diploma', 'Diploma level', 'select', True, {'licence': 'Licence / Bachelor', 'maitrise': 'Maîtrise / Master', 'engineer': 'Engineer', 'phd': 'Doctorate', 'other': 'Other'}),
     fld('field', 'Field of study (e.g. civil engineering, law, linguistics)', 'text', True),
     fld('macro', 'Macro work you prefer', 'select', False, {'': 'Any', 'cultures': 'Cultures / Humanities', 'built': 'Built Environment', 'sciences': 'Sciences', 'governance': 'Governance', 'technology': 'Technology'}),
     fld('email', 'Email (optional, to receive the list)', 'email')], 'Show my services'),
 ])
page('app-register', 'Register as an expert applicant', 'Open an applicant file. Registration is not a job offer.',
 [
  hero('reg-hero', 'Open your **applicant file.**', 'One file, one identity. Registration starts certification; it is not a job offer.', [cta('Open the form', '#reg-form', 'peak', True)], eyebrow='Register', gradient='technology', note='A part is an investment in a path, not a job.'),
  form('reg-form', '/app/register', 'Applicant file', [
     fld('name', 'Full name', 'text', True), fld('email', 'Email', 'email', True), fld('phone', 'Phone', 'tel'),
     fld('region', 'Governorate', 'select', True, {'': 'Choose…'}),
     fld('diploma', 'Diploma level', 'select', True, {'licence': 'Licence / Bachelor', 'maitrise': 'Maîtrise / Master', 'engineer': 'Engineer', 'phd': 'Doctorate', 'other': 'Other'}),
     fld('field', 'Field of study', 'text', True), fld('service', 'Service of interest (optional)', 'text'),
     fld('accept', 'I have read the [declared limits](/trust/statement/).', 'checkbox', True, span=2)], 'Register'),
 ])
page('join-thanks', 'Thank you', 'Your request is recorded.', [
  hero('thx-hero', 'Thank you. **It is recorded.**', 'We keep what you sent, and nothing more. A reviewer answers within five working days.', [cta('Back home', '/', 'focus', True), cta('Share the programme', '/join/', 'ghost', False)], eyebrow='Done', gradient='cultures')], robots='noindex,follow')

# ===== HOW IT WORKS =====
page('how-it-works', 'How it works — from diploma to weekly delivery',
 'Four levels of certification, weekly reports due Friday 12:00, 5% random sampling, public rollups.',
 [
  hero('how-hero', 'How it works, **step by step.**', 'Four certification levels. A weekly cycle. A {{ quality.sampling_percent }}% random audit. A public rollup.', [cta('Start L1', '/join/eligibility/', 'peak', True)], eyebrow='Method', gradient='sciences'),
  steps('how-levels', 'Four certification levels', [
     ('L1 Foundations', 'Vocabulary, evidence rules and the boundary of your service.', None),
     ('L2 Practice', 'A supervised case with a real acceptance rubric.', None),
     ('L3 Delivery', 'A full weekly unit with a named reviewer.', None),
     ('L4 Review', 'Reviewing others\' units; eligibility to be a manager candidate.', None)], tier='i', focus=3),
  defs('how-week', 'The weekly cycle', [
     ('Monday', 'Directives issued per team.'), ('Friday 12:00', 'Reports due.'), ('Friday–Sunday', 'Manager review and approvals.'),
     ('Lock', 'The week is locked; edits become dated corrections.'), ('Rollup', 'Counts and KPIs are written to the daily rollup.'),
     ('Archive', 'Evidence is archived with a manifest.')], tier='i', focus=2),
  prose('how-sampling', '{{ quality.sampling_percent }}% sampling, honestly', [
     'A random {{ quality.sampling_percent }}% of reports is re-checked against source evidence each week. That is about {{ d.reviews_per_week }} reviews a week at the design ceiling.',
     'Sampling does not replace manager review of every report. It exists so that nobody knows which report will be re-checked.'], tier='x', focus=1, ctas=[cta('Sampling details', '/trust/sampling/', 'link', True)]),
  fcta('how-final', 'Ready to start **L1?**', 'Check your eligibility first.', [cta('Check my eligibility', '/join/eligibility/', 'peak', True)], 'sciences'),
 ])

# ===== PARTNERS =====
page('partners', 'Partners and clients — commission a delivery',
 'Commission a scoped delivery from a service team with a named beneficiary, an acceptance rule and a dated report.',
 [
  hero('par-hero', 'Commission a delivery. **Accept against a rule.**', 'Every engagement has a named beneficiary, a definition of the deliverable unit and an acceptance rubric agreed before work starts.', [cta('Book a scoping call', '/contact/?desk=partners', 'peak', True)], eyebrow='Partners', gradient='built'),
  cards('par-how', 'What a scoped delivery contains', [
     {'icon': 'target', 'title': 'Deliverable unit', 'body': 'For example capture packages, condition surveys or option briefs, with a weekly target.'},
     {'icon': 'clipboard-check', 'title': 'Acceptance rule', 'body': 'Evidence format, reviewer, version and exception log.'},
     {'icon': 'user-round-check', 'title': 'Named beneficiary', 'body': 'An advisory product without a named public-service partner is not assumed to help anyone.'}], tier='i', focus=3),
  notice('par-limits', ['W5D supports evidence and referrals. Only authorised bodies adopt law, set tariffs or award accreditation.', 'Licence for the Admin Dashboard: {{ money.license_hour }} {{ money.currency }} per hour of work.']),
  fcta('par-final', 'Tell us the **problem.** We will scope the unit.', 'A scoping call is free.', [cta('Book a scoping call', '/contact/?desk=partners', 'peak', True)], 'built'),
 ])

# ===== ABOUT / ONTOLOGY / TUNISIA =====
page('about', 'About W5D — a company and a working ontology',
 'W5D is a company and a working ontology: the fourth dimension is information and structure, the fifth is possibility and modality.',
 [
  hero('abt-hero', 'A company and a **working ontology.**', 'W5D is World Five Dimensions: a company that publishes its own structure, its dated status and its limits.', [cta('Read the declared limits', '/trust/statement/', 'peak', True)], eyebrow='About', gradient='calm'),
  prose('abt-story', 'What W5D is, and is not', [
     'W5D organises demand for expert work into five macro works and {{ counts.services }} services, and a path by which graduates become supervised contributors.',
     'It is status-transparent to the point of self-limitation: it lists drafts, concepts and live items with dates, and says "awaiting" where there is no evidence yet.',
     'It does not claim that {{ d.experts_target }} experts are active. {{ experts.developers_today }} developers deliver in Tunisia today.'], tier='e', focus=3),
  {'type': 'project-cards', 'id': 'abt-projects', 'tier': 'i', 'focus': 2, 'title': L('What exists, with dates'), 'limit': 6, 'only': ['meta-x-academy', 'ontology-department', 'studio', 'developers-tunisia', 'departments', 'dyon'], 'ctas': [cta('All projects', '/projects/', 'link', True)]},
  fcta('abt-final', 'Join the work, **or fund it.**', 'Both start from the same page of limits.', [INV, cta('Apply as a manager', '/careers/managers/', 'ghost', False)], 'peak'),
 ])
page('ontology', 'Ontology — six layers, five macros, 130 services',
 'The W5D ontology: six layers from doctrine to programme, five macro works and {{ counts.services }} services.',
 [
  hero('ont-hero', 'Six layers. Five macros. **{{ counts.services }} services.**', 'The ontology is the structure behind every page. Layers L0 to L5 say what kind of statement a page makes.', [cta('Browse the services', '/services/', 'peak', True)], eyebrow='Ontology', gradient='sciences'),
  {'type': 'stack', 'id': 'ont-stack', 'tier': 'x', 'focus': 2, 'title': L('The six layers'), 'lead': L('Every claim on the site belongs to exactly one layer.')},
  {'type': 'macro-cards', 'id': 'ont-macros', 'tier': 'i', 'focus': 3, 'title': L('The five macro works (L3)')},
  defs('ont-rules', 'Rules that keep it coherent', [
     ('One service, one unit', 'Every service defines its deliverable unit and weekly target.'),
     ('Cross-macro dependency', 'Every service names at least one input provider and one downstream consumer.'),
     ('Evidence rule', 'Every service states what an accepted package contains.'),
     ('Boundary', 'Every service declares what it will not substitute for.'),
     ('No rename', 'Services, macros and projects keep their slugs.')]),
  fcta('ont-final', 'Find **your** service.', '', [cta('Browse the services', '/services/', 'peak', True)], 'sciences'),
 ])
page('countries-tunisia', 'Tunisia hub — 24 governorates',
 'Tunisia country hub: legal profile, salary model, calendar and the 24 governorates.',
 [
  hero('tn-hero', 'Tunisia, **governorate by governorate.**', 'Register from your governorate. Hubs, universities and coordinators are listed as they are confirmed, not before.', [cta('Find my governorate', '#tn-map', 'peak', True)], eyebrow='Country hub', gradient='built'),
  {'type': 'gov-map', 'id': 'tn-map', 'tier': 'e', 'focus': 4, 'title': L('{{ counts.governorates }} governorates')},
  defs('tn-profile', 'Country profile', [
     ('Currency', '{{ money.currency }}'), ('Timezone', 'Africa/Tunis'), ('Salary model', '{{ money.salary_monthly }} {{ money.currency }} per expert post per 30-day month'),
     ('Data protection', 'Organic Law 2004-63; INPDP'), ('Languages', 'Arabic, French, English')], lead='Profile data comes from the country file.'),
  fcta('tn-final', 'Register from **your** governorate.', '', [cta('Register now', '/app/register', 'peak', True)], 'built'),
 ])

# ===== FAQ / CONTACT / STATUS / NEWSLETTER =====
page('faq', 'FAQ — straight answers', 'Frequently asked questions for investors, managers and graduates.', [
  hero('faq-hero', 'Straight **answers.**', 'If an answer is missing, ask. We publish corrections within 14 days.', [cta('Ask a question', '/contact/', 'peak', True)], eyebrow='FAQ', gradient='calm'),
  faq('faq-investors', 'Investors', [
     ('How much is a part?', '{{ price.day }} {{ money.currency }} per day today, rising with progress and time up to {{ pricing.cap }}× base.'),
     ('Is anything guaranteed?', 'No return, no resale value, no employment. See the declared limits.'),
     ('Can I buy every day?', 'Yes, with a daily subscription. You see the full schedule and can pause or cancel.')], ctas=[INV]),
  faq('faq-managers', 'Managers', [
     ('Is a post promised?', 'No. Posts follow certified headcount and funded capacity.'),
     ('How is the scenario scored?', 'Against a published rubric on judgement, evidence and honesty.')], ctas=[cta('Apply as a manager', '/careers/managers/', 'link', True)]),
  faq('faq-graduates', 'Graduates', [
     ('Do I pay to join?', 'No. Certification is free for applicants.'),
     ('Will I get a job?', 'W5D does not promise employment. It offers a supervised path with dated evidence.')], ctas=[cta('Check my eligibility', '/join/eligibility/', 'link', True)]),
  fcta('faq-final', 'Still unsure? **Write to the right desk.**', '', [cta('Contact', '/contact/', 'peak', True)], 'calm'),
 ])
page('contact', 'Contact — write to the right desk', 'Contact desks: investors, managers, partners, press, corrections.', [
  hero('con-hero', 'Write to the **right desk.**', 'Pick a desk so the right person answers. Investors and manager candidates get a reply within five working days.', [cta('Open the form', '#con-form', 'peak', True)], eyebrow='Contact', gradient='warm'),
  {'type': 'contact-desks', 'id': 'con-desks', 'tier': 'e', 'focus': 3, 'title': L('Desks'), 'items': [
     {'icon': 'coins', 'title': L('Investors'), 'body': L('Dossier, schedule and legal questions.'), 'href': '/contact/?desk=investors'},
     {'icon': 'users', 'title': L('Managers'), 'body': L('Application status and scoring.'), 'href': '/careers/managers/'},
     {'icon': 'handshake', 'title': L('Partners'), 'body': L('Scoping a delivery.'), 'href': '/contact/?desk=partners'},
     {'icon': 'newspaper', 'title': L('Press'), 'body': L('Facts, dates, figures.'), 'href': '/contact/?desk=press'},
     {'icon': 'file-pen', 'title': L('Corrections'), 'body': L('Report an error. Answered in 14 days.'), 'href': '/trust/corrections/'},
     {'icon': 'mail', 'title': L('Direct'), 'body': L('{{ investor.contact }}'), 'href': 'mailto:{{ investor.contact }}'}]},
  form('con-form', '/contact/', 'Your message', [
     fld('name', 'Full name', 'text', True), fld('email', 'Email', 'email', True),
     fld('desk', 'Desk', 'select', True, {'investors': 'Investors', 'managers': 'Managers', 'partners': 'Partners', 'press': 'Press', 'general': 'General'}),
     fld('subject', 'Subject', 'text'), fld('message', 'Message', 'textarea', True, span=2, rows=6)], 'Send'),
 ])
page('newsletter', 'Newsletter — dated updates only', 'Subscribe to dated programme updates.', [
  hero('nl-hero', 'Dated updates. **Nothing else.**', 'One email when something real changes: a status, a price constant, a published rollup.', [cta('Subscribe', '#nl-form', 'peak', True)], eyebrow='Newsletter', gradient='cultures'),
  form('nl-form', '/newsletter/', 'Subscribe', [fld('email', 'Email', 'email', True), fld('accept', 'I agree to receive dated programme updates.', 'checkbox', True)], 'Subscribe'),
 ])
page('status', 'Status — dated and sourced', 'Public status of the programme: figures, dates and sources.', [
  hero('st-hero', 'Status, **with dates.**', 'Every figure carries its date and its source. Awaiting means awaiting.', [cta('Read the limits', '/trust/statement/', 'peak', True)], eyebrow='Status', gradient='calm'),
  {'type': 'status-live', 'id': 'st-live', 'tier': 'x', 'focus': 2, 'title': L('Live figures')},
  {'type': 'register', 'id': 'st-register', 'tier': 'x', 'focus': 2, 'title': L('Register'), 'rows': [
     {'status': L('Live'), 'name': L('MetaX Academy'), 'date': L('{{ dates.as_of }}'), 'evidence': L('Listed on the public preview.')},
     {'status': L('Live'), 'name': L('Ontology Department'), 'date': L('{{ dates.as_of }}'), 'evidence': L('{{ counts.services }} services registered.')},
     {'status': L('Live'), 'name': L('Developers (Tunisia)'), 'date': L('{{ dates.as_of }}'), 'evidence': L('{{ experts.developers_today }} developers.')},
     {'status': L('Awaiting'), 'name': L('First certified experts'), 'date': L('{{ dates.as_of }}'), 'evidence': L('No certification cohort has completed yet.')},
     {'status': L('Awaiting'), 'name': L('First reviews'), 'date': L('{{ dates.as_of }}'), 'evidence': L('Awaiting the first review.')},
     {'status': L('Pending'), 'name': L('Payment provider'), 'date': L('{{ dates.as_of }}'), 'evidence': L('Demo mode. No payment is taken.')}]},
 ])

# ===== TRUST =====
page('trust', 'Trust and limits', 'Declared limits, sampling method, privacy, terms, security and corrections.', [
  hero('tr-hero', 'Trust is a **list of limits.**', 'We publish what we do not claim, how we check ourselves and how to correct us.', [cta('Declared limits', '/trust/statement/', 'peak', True)], eyebrow='Trust', gradient='calm'),
  cards('tr-index', 'Trust pages', [
     {'icon': 'scale', 'title': 'Declared limits', 'body': 'No job, no return, no headcount claim.', 'href': '/trust/statement/', 'cta': 'Read'},
     {'icon': 'file-search', 'title': '5% sampling', 'body': 'How reports are re-checked.', 'href': '/trust/sampling/', 'cta': 'Read'},
     {'icon': 'lock', 'title': 'Privacy', 'body': 'What we collect and keep.', 'href': '/trust/privacy/', 'cta': 'Read'},
     {'icon': 'file-text', 'title': 'Terms', 'body': 'Demo-mode terms for pledges.', 'href': '/trust/terms/', 'cta': 'Read'},
     {'icon': 'shield', 'title': 'Security', 'body': 'Controls and disclosure.', 'href': '/trust/security/', 'cta': 'Read'},
     {'icon': 'file-pen', 'title': 'Corrections', 'body': 'Report an error; 14-day answer.', 'href': '/trust/corrections/', 'cta': 'Read'}], tier='e', focus=3)])
page('trust-statement', 'Declared limits', 'What W5D does not promise: no job, no return, no headcount claim, and how the price works.', [
  hero('ts-hero', 'Declared **limits.**', 'Read this before you invest, apply or share.', [cta('Back to investing', '/investors/', 'peak', True)], eyebrow='Statement', gradient='calm'),
  prose('ts-jobs', '1. A part is an investment in a path, not a job', ['W5D does not promise employment to anyone who buys a part, registers or is certified. Posts are created as certified headcount and funded capacity grow.'], tier='e', focus=3),
  prose('ts-design', '2. The headcount is a design equation', ['"{{ d.experts_target }} experts" is {{ counts.macros }} × {{ experts.services_per_macro }} × {{ experts.per_service }}. It is a design ceiling, not a claim of employment.', 'Today: {{ experts.developers_today }} developers deliver in Tunisia; {{ d.experts_current }} experts are certified (as of {{ dates.as_of }}).'], tier='e', focus=2),
  prose('ts-returns', '3. No return is promised', ['No return, dividend or resale value is promised. A part funds a published programme at a published price.'], tier='e', focus=2),
  prose('ts-price', '4. The price function', ['price = base × (1 + α·p^k) × (1 + β·t^m), capped at {{ pricing.cap }}× base, with base {{ money.base_day }} {{ money.currency }} per day. The curve is public; constants change only with a dated, logged entry.'], tier='i', focus=2, ctas=[cta('See the curve', '/pricing/', 'link', True)]),
  prose('ts-demo', '5. Demo mode', ['While the payment provider is not connected, pledges are recorded and no payment is taken. Terms for real payments will be shown before you confirm.'], tier='e', focus=2),
  prose('ts-offer', '6. Dates', ['Programme clock: {{ dates.start }} to {{ dates.deadline }}. Promotional offer on the Admin Dashboard licence valid before {{ dates.offer_year }}.'], tier='i', focus=1),
  fcta('ts-final', 'Understood? **Continue.**', '', [INV, cta('File a correction', '/trust/corrections/', 'ghost', False)], 'peak'),
 ])
page('trust-sampling', '5% sampling', 'How a random 5% of reports is re-checked.', [
  hero('sp-hero', '{{ quality.sampling_percent }}% sampling, **explained.**', 'A random subset of weekly reports is re-checked against source evidence.', [cta('How it works', '/how-it-works/', 'peak', True)], eyebrow='Quality', gradient='sciences'),
  prose('sp-body', 'What it is', ['Each week {{ quality.sampling_percent }}% of reports are drawn at random and re-checked against the source evidence. At the design ceiling that is about {{ d.reviews_per_week }} reviews a week.', 'Sampling does not replace manager review of every report; it makes weak reports risky for everyone.', 'The rate is fixed by doctrine and appears on every page that mentions it.'], tier='x', focus=2)])
page('trust-privacy', 'Privacy', 'What W5D collects, why, and for how long.', [
  hero('pv-hero', 'Privacy, **in plain terms.**', 'We collect what a form asks for, and nothing else. No third-party trackers, no CDN, no fonts from elsewhere.', [cta('Contact the privacy desk', '/contact/', 'peak', True)], eyebrow='Privacy', gradient='calm'),
  prose('pv-body', 'What we keep', ['Form submissions are stored in our own database. We use one session cookie for security and one for your language choice.', 'Applicant data follows Organic Law 2004-63 and INPDP rules. You may ask for access, correction or deletion at {{ investor.contact }}.', 'We do not sell data. We do not load third-party scripts.'], tier='e', focus=2)])
page('trust-terms', 'Terms', 'Demo-mode terms for pledges and subscriptions.', [
  hero('tm-hero', 'Terms, **demo mode.**', 'These terms apply while no payment provider is connected.', [cta('Declared limits', '/trust/statement/', 'peak', True)], eyebrow='Terms', gradient='calm'),
  prose('tm-body', 'Pledges and subscriptions', ['A pledge records an intention at a dated price. No payment is taken and no money moves in demo mode.', 'You may pause, resume or cancel with your management key at any time. Cancelling stops all future days.', 'Real-payment terms will be published and shown before any charge.'], tier='i', focus=2)])
page('trust-security', 'Security', 'Security controls and responsible disclosure.', [
  hero('sc-hero', 'Security, **checkable.**', 'Strict CSP, no inline scripts, CSRF tokens, rate limits, hashed secrets, an obfuscated owner mount.', [cta('Report a vulnerability', '/trust/corrections/', 'peak', True)], eyebrow='Security', gradient='technology'),
  defs('sc-list', 'Controls', [('CSP', "script-src 'self'; no inline scripts"), ('CSRF', 'Token on every state-changing form'), ('Passwords', 'Argon2id; owner secret from the environment'), ('Rate limits', 'Per IP and action'), ('Cookies', 'HttpOnly, SameSite, Secure over HTTPS'), ('Dependencies', 'No Composer, no CDN')], tier='x', focus=2)])
page('trust-corrections', 'Corrections', 'Report an error. We answer within 14 days.', [
  hero('co-hero', 'Found an error? **Tell us.**', 'Corrections are logged with a date and answered within 14 days.', [cta('Open the form', '#co-form', 'peak', True)], eyebrow='Corrections', gradient='calm'),
  form('co-form', '/contact/', 'Report an error', [fld('name', 'Full name', 'text', True), fld('email', 'Email', 'email', True), fld('desk', 'Desk', 'select', True, {'corrections': 'Corrections'}), fld('subject', 'Page or figure', 'text', True), fld('message', 'What is wrong, and what is your source?', 'textarea', True, span=2, rows=5)], 'Send')])

# ===== CHECKOUT chrome (wizard rendered by controller) =====
page('checkout', 'Checkout — take a part today', 'Three-step checkout wizard: your details, your parts, review and confirm. Demo mode.', [
  hero('co-top', 'Take a part. **Three steps.**', 'Details, parts and schedule, review. The live price is shown before every decision.', [cta('Start', '#chk-wizard', 'peak', True)], eyebrow='Checkout', gradient='peak', note='Demo mode: no payment is taken.'),
  {'type': 'wizard', 'id': 'chk-wizard', 'mode': 'checkout', 'tier': 'e', 'focus': 4, 'title': L('Your part')},
  notice('chk-limits')])
page('checkout-done', 'Pledge recorded', 'Your pledge is recorded.', [], robots='noindex,follow')
page('subscription-manage', 'Manage your subscription', 'Pause, resume or cancel a subscription with your reference and management key.', [
  hero('mng-hero', 'Manage your **subscription.**', 'Enter your reference and management key.', [cta('Open', '#mng-form', 'peak', True)], eyebrow='Manage', gradient='cultures')], robots='noindex,follow')
print('gen3 ok')
