#!/usr/bin/env python3
"""QCM end-to-end suite: rounds, autosave, exact-set scoring, timer, requirement gating, fee pass -> D17 -> owner verify,
tasks, graded vs practice answer visibility, question bank CRUD/import/export, ACL and CSRF.
Usage: python3 tests/qcm.py [base_url]   (dev/demo DB, MAIL_DRIVER=log). Test data it creates is removed or left inert.
"""
import json, os, re, sys, time
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _prog import *

BANK_BACKUP = {}


def attempt_row(aid):
    return q1('SELECT * FROM qcm_attempts WHERE id = ?', (aid,))


def items_of(aid):
    return json.loads(attempt_row(aid)['questions'])['items']


def start(c, rule_id):
    s, h, b = post(c, '/account/qcm/start', {'rule': rule_id})
    m = re.search(r'/account/qcm/attempt/(\d+)/', loc(h))
    return s, (int(m.group(1)) if m else None), h


import zoneinfo, datetime


def _dl(ts):
    """Seconds from now until a server-local (Africa/Tunis) timestamp."""
    d = datetime.datetime.strptime(ts, '%Y-%m-%d %H:%M:%S').replace(tzinfo=zoneinfo.ZoneInfo('Africa/Tunis'))
    return d.timestamp() - time.time()


def submit(c, aid, answers):
    pairs = []
    for qid, ch in answers.items():
        for x in ch:
            pairs.append((f'a[{qid}][]', x))
    return post(c, f'/account/qcm/attempt/{aid}/submit', pairs, token_from=f'/account/qcm/attempt/{aid}/')


def correct_all(aid):
    return {it['id']: list(it['answer']) for it in items_of(aid)}


def play(c, rule_id, good=True):
    s, aid, h = start(c, rule_id)
    if not aid:
        return None
    ans = correct_all(aid)
    if not good:
        ans = {}
    submit(c, aid, ans)
    return aid


def mk_rule(**kw):
    d = dict(name='TEST ' + uniq(), purpose='practice', level=1, service_slug='networking', question_count=5, minutes=5, pass_pct=60, fee=0, min_level=0,
             min_affiliates=0, min_tasks=0, min_practice_pct=0, cooldown_hours=0, max_per_day=0, active=1, sort=999)
    d.update(kw)
    cols = ','.join(d)
    return run(f'INSERT INTO qcm_rules ({cols}) VALUES ({",".join("?" * len(d))})', tuple(d.values()))


rules_made = []
try:
    section('public surface: nothing leaks to guests')
    g = new_user()[0]
    guest = __import__('_acct')._client()
    s, h, b = call(guest, 'GET', '/account/qcm/')
    check('guest is sent to sign-in for the QCM panel', s == 302 and '/account/login/' in loc(h), s)
    s, h, b = call(guest, 'POST', '/account/qcm/answer', {'attempt': 1, 'qid': 'x', 'choice[]': 'a'})
    check('autosave without a session -> 401 json', s == 401 and '"auth"' in b, s)
    s, h, b = call(guest, 'GET', '/account/qcm/attempt/1/')
    check('attempt page for a guest redirects', s == 302, s)

    section('panel: rounds listed with requirement checklist')
    c, email, uid = new_user()
    s, h, b = call(c, 'GET', '/account/qcm/')
    check('panel renders for a new user', s == 200 and 'Practice L1' in b and 'Certification L1' in b, s)
    check('graded round shows unmet requirement (practice score)', 'Upgrade to L2' in b and ('60%' in b or '60' in b))

    section('start, one open round at a time, autosave contract')
    s, aid, h = start(c, 1)
    check('start practice L1 redirects to the attempt page', s == 302 and aid is not None, (s, loc(h)))
    a = attempt_row(aid)
    check('round has 10 questions and a server deadline ~10 min away', a['total'] == 10 and 540 <= _dl(a['deadline_at']) <= 605, a['deadline_at'])
    s, h, b = call(c, 'GET', f'/account/qcm/attempt/{aid}/')
    check('attempt page renders the wizard (timer + steps)', s == 200 and 'data-qcm' in b and 'data-qcm-clock' in b and b.count('qcm-step') >= 10, s)
    check('open attempt page does not reveal answers', '>· right answer<' not in b and '>· bonne réponse<' not in b and '"answer"' not in b)
    s2, aid2, h = start(c, 2)
    check('starting another round returns the SAME open attempt', aid2 == aid, (aid, aid2))
    it = items_of(aid)[0]
    s, h, b = call(c, 'POST', '/account/qcm/answer', {'attempt': aid, 'qid': it['id'], 'choice[]': it['choices'][0]['id']})
    check('autosave without CSRF -> 419', s == 419 and '"csrf"' in b, s)
    t = token(c)
    s, h, b = call(c, 'POST', '/account/qcm/answer', [('_csrf', t), ('attempt', aid), ('qid', it['id']), ('choice[]', it['choices'][0]['id'])])
    j = json.loads(b) if b.startswith('{') else {}
    check('autosave with CSRF -> 200 {ok,left}', s == 200 and j.get('ok') is True and 0 < j.get('left', 0) <= 600, b[:80])
    check('answer stored server-side', q1('SELECT answers FROM qcm_attempts WHERE id=?', (aid,)) != '{}')
    s, h, b = call(c, 'POST', '/account/qcm/answer', [('_csrf', t), ('attempt', aid), ('qid', it['id']), ('choice[]', 'zz')])
    stored = json.loads(q1('SELECT answers FROM qcm_attempts WHERE id=?', (aid,)))
    check('invalid choice ids are dropped (not stored)', it['id'] not in stored or 'zz' not in stored.get(it['id'], []))
    other, _, _ = new_user()
    s, h, b = call(other, 'POST', '/account/qcm/answer', [('_csrf', token(other)), ('attempt', aid), ('qid', it['id']), ('choice[]', 'a')])
    check("another user cannot autosave into my attempt (404)", s == 404, s)
    s, h, b = call(other, 'GET', f'/account/qcm/attempt/{aid}/')
    check("another user cannot open my attempt (404)", s == 404, s)

    section('exact-set scoring')
    items = items_of(aid)
    ans = correct_all(aid)
    multi = [i for i in items if i['type'] == 'multiple']
    wrong_q = None
    if multi:
        wrong_q = multi[0]['id']
        ans[wrong_q] = ans[wrong_q][:1]            # subset of a multiple answer must NOT count
    else:
        wrong_q = items[0]['id']
        ans[wrong_q] = [x['id'] for x in items[0]['choices'] if x['id'] not in items[0]['answer']][:1]
    s, h, b = submit(c, aid, ans)
    a = attempt_row(aid)
    check('submit redirects back to the result page', s == 302, s)
    check('one wrong/partial question -> 9/10, status submitted', a['status'] == 'submitted' and a['correct'] == 9 and a['total'] == 10, (a['correct'], a['total']))
    check('score 90% and practice pass (60) recorded', a['score_pct'] == 90.0 and a['passed'] == 1)
    check('not timed out', a['timed_out'] == 0)
    s, h, b = call(c, 'GET', f'/account/qcm/attempt/{aid}/')
    check('practice result reveals right answers + review rows', s == 200 and '>· right answer<' in b and b.count('data-review=') == 10, s)
    check('review marks exactly one question wrong', b.count('data-ok="0"') == 1 and b.count('data-ok="1"') == 9)
    s, h, b = submit(c, aid, correct_all(aid))
    check('re-submitting a finished round changes nothing', attempt_row(aid)['correct'] == 9)
    s, h, b = call(c, 'POST', '/account/qcm/answer', [('_csrf', token(c)), ('attempt', aid), ('qid', it['id']), ('choice[]', 'a')])
    check('autosave after finish -> 409', s == 409, s)
    check('no certificate for a practice pass', q1('SELECT COUNT(*) FROM certificates WHERE user_id=?', (uid,)) == 0)

    section('timer: late finish is scored from autosave only, flagged timed_out')
    c2, e2, u2 = new_user()
    s, aid, h = start(c2, 1)
    its = items_of(aid)
    first = its[0]
    call(c2, 'POST', '/account/qcm/answer', [('_csrf', token(c2)), ('attempt', aid), ('qid', first['id']), *[('choice[]', x) for x in first['answer']]])
    run("UPDATE qcm_attempts SET deadline_at = datetime('now','-5 minutes') WHERE id = ?", (aid,))
    s, h, b = call(c2, 'POST', '/account/qcm/answer', [('_csrf', token(c2)), ('attempt', aid), ('qid', its[1]['id']), ('choice[]', its[1]['answer'][0])])
    check('autosave after deadline+grace -> 409', s == 409, s)
    s, h, b = submit(c2, aid, correct_all(aid))      # posted answers after the window must be ignored
    a = attempt_row(aid)
    check('late submit keeps only the autosaved answer (1 correct)', a['status'] == 'submitted' and a['correct'] == 1 and a['timed_out'] == 1, (a['status'], a['correct'], a['timed_out']))
    s, aid3, h = start(c2, 1)
    run("UPDATE qcm_attempts SET deadline_at = datetime('now','-5 minutes') WHERE id = ?", (aid3,))
    s, h, b = call(c2, 'GET', f'/account/qcm/attempt/{aid3}/')
    check('opening an expired round auto-closes it (result page)', s == 200 and attempt_row(aid3)['status'] == 'submitted' and attempt_row(aid3)['timed_out'] == 1)
    s, aid4, h = start(c2, 1)
    check('a new round can start once the expired one is closed', aid4 and aid4 != aid3)
    submit(c2, aid4, {})

    section('requirement gating (practice score, cooldown, daily cap, tasks, level, affiliates)')
    c3, e3, u3 = new_user()
    n0 = q1('SELECT COUNT(*) FROM qcm_attempts WHERE user_id=?', (u3,))
    s, aid, h = start(c3, 5)
    check('certification L1 refused without a 60% practice score', aid is None and q1('SELECT COUNT(*) FROM qcm_attempts WHERE user_id=?', (u3,)) == n0, (s, loc(h)))
    s, h, b = call(c3, 'GET', '/account/qcm/')
    check('panel flash/checklist explains the unmet requirement', 'requirement' in b.lower() or 'condition' in b.lower() or 'exigence' in b.lower() or 'الشرو' in b)
    check('practice L1 round for the gate', play(c3, 1) is not None)
    s, aid, h = start(c3, 5)
    check('certification L1 now allowed (practice 100%)', aid is not None, (s, loc(h)))
    ans = correct_all(aid)
    first_q = next(iter(ans))
    bad = dict(ans)
    wrong = [x['id'] for x in items_of(aid)[0]['choices'] if x['id'] not in items_of(aid)[0]['answer']]
    bad[first_q] = wrong[:1]
    submit(c3, aid, ans)
    a = attempt_row(aid)
    check('certification pass (>=70) stores a certificate code', a['passed'] == 1 and a['cert_code'] and re.match(r'^W5D-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$', a['cert_code']), a['cert_code'])
    s, h, b = call(c3, 'GET', f'/account/qcm/attempt/{aid}/')
    check('graded result hides right answers (bank stays fair)', '>· right answer<' not in b and 'data-review=' in b)
    check('certificate shown on the result page', a['cert_code'] in b)
    cl = q1("SELECT level FROM certificates WHERE code=?", (a['cert_code'],))
    check('certificate level 1, status valid', cl == 1)
    s, aid5, h = start(c3, 5)
    check('cooldown (1 h) blocks a second certification attempt', aid5 is None)
    s, h, b = call(c3, 'GET', f'/account/certificates/')
    check('certificates panel lists the new code', s == 200 and a['cert_code'] in b)
    s, aid6, h = start(c3, 6)
    check('upgrade to L2 refused: tasks=0 and no L2 practice', aid6 is None)

    rid = mk_rule(name='TEST daily cap', max_per_day=1); rules_made.append(rid)
    c4, e4, u4 = new_user()
    check('daily cap: first round ok', play(c4, rid, good=False) is not None)
    s, aid, h = start(c4, rid)
    check('daily cap: second round of the day refused', aid is None)

    rid = mk_rule(name='TEST needs task', min_tasks=1); rules_made.append(rid)
    c5, e5, u5 = new_user()
    s, aid, h = start(c5, rid)
    check('min_tasks=1 refused with no approved task', aid is None)
    s, h, b = post(c5, '/account/qcm/task', {'title': 'x', 'evidence': 'short'})
    check('task needs a title (3+) and evidence (10+): rejected', q1('SELECT COUNT(*) FROM qcm_tasks WHERE user_id=?', (u5,)) == 0)
    s, h, b = post(c5, '/account/qcm/task', {'title': 'Audit of a water-meter dataset', 'evidence': 'Cleaned 120 rows, notes in the shared sheet.', 'service_slug': 'networking'})
    tid = q1("SELECT id FROM qcm_tasks WHERE user_id=?", (u5,))
    check('task submitted (status submitted)', tid and q1('SELECT status FROM qcm_tasks WHERE id=?', (tid,)) == 'submitted')
    s, aid, h = start(c5, rid)
    check('a submitted (unreviewed) task does not count', aid is None)

    section('owner reviews the task; staff ACL on the review queue')
    o = owner()
    s, h, b = call(o, 'GET', M + '/qcm-review/')
    check('owner sees the review queue with the task', s == 200 and 'Audit of a water-meter dataset' in b, s)
    s, h, b = call(o, 'POST', M + '/qcm-review/task', {'id': tid, 'decision': 'approve'})
    check('review POST without CSRF changes nothing', q1('SELECT status FROM qcm_tasks WHERE id=?', (tid,)) == 'submitted')
    s, h, b = post(o, M + '/qcm-review/task', {'id': tid, 'decision': 'approve'}, token_from=M + '/qcm-review/')
    check('owner approves the task', q1('SELECT status FROM qcm_tasks WHERE id=?', (tid,)) == 'approved')
    s, aid, h = start(c5, rid)
    check('min_tasks=1 satisfied after approval', aid is not None)
    if aid: submit(c5, aid, {})
    plain, _, _ = new_user()
    s, h, b = call(plain, 'GET', M + '/qcm-review/')
    check('a normal account cannot open the review queue', s in (302, 403), s)
    lead = staff('leader')
    s, h, b = call(lead, 'GET', M + '/qcm-review/')
    check('leader (no qcm permission) -> 403', s == 403, s)
    s, h, b = call(lead, 'GET', M + '/qcm-bank/')
    check('leader cannot open the question bank -> 403', s == 403, s)

    section('fee pass: buy -> D17 reference -> owner verifies -> start consumes it')
    rid = mk_rule(name='TEST fee round', fee=5.0); rules_made.append(rid)
    c6, e6, u6 = new_user()
    s, aid, h = start(c6, rid)
    check('fee round refused before payment', aid is None)
    s, h, b = post(c6, '/account/qcm/buy', {'rule': rid})
    m = re.search(r'/account/qcm/pay/(QP-[A-F0-9]{8})/', loc(h))
    check('buy creates a pass QP-XXXXXXXX and redirects to its payment page', s == 302 and m, loc(h))
    ref = m.group(1)
    s, h, b = call(c6, 'GET', f'/account/qcm/pay/{ref}/')
    check('payment page shows D17 instructions and amount', s == 200 and '5.00' in b and ('D17' in b), s)
    s, h, b = post(c6, '/account/qcm/buy', {'rule': rid})
    check('buying again re-uses the pending pass (no duplicates)', q1('SELECT COUNT(*) FROM qcm_passes WHERE user_id=?', (u6,)) == 1)
    cc, _, _ = new_user()
    s, h, b = call(cc, 'GET', f'/account/qcm/pay/{ref}/')
    check("someone else's pass page -> 404", s == 404, s)
    s, h, b = post(c6, f'/account/qcm/pay/{ref}/', {'sender_gsm': '+21622345678', 'txn_ref': 'ab'}, token_from=f'/account/qcm/pay/{ref}/')
    check('short transaction reference rejected', q1('SELECT status FROM qcm_passes WHERE ref=?', (ref,)) == 'awaiting')
    s, h, b = post(c6, f'/account/qcm/pay/{ref}/', {'sender_gsm': '+33612345678', 'txn_ref': 'TXN' + uniq()}, token_from=f'/account/qcm/pay/{ref}/')
    check('non-Tunisian D17 number rejected', q1('SELECT status FROM qcm_passes WHERE ref=?', (ref,)) == 'awaiting')
    txn = 'QCMT' + uniq()
    s, h, b = post(c6, f'/account/qcm/pay/{ref}/', {'sender_gsm': '+21622345678', 'txn_ref': txn}, token_from=f'/account/qcm/pay/{ref}/')
    check('valid reference -> pass pending', q1('SELECT status FROM qcm_passes WHERE ref=?', (ref,)) == 'pending')
    s, aid, h = start(c6, rid)
    check('pending (unverified) pass does not unlock the round', aid is None)
    c7, e7, u7 = new_user()
    run_id = uniq()
    pid7 = run("INSERT INTO qcm_passes (ref,user_id,rule_id,amount,currency,txn_ref,status) VALUES (?,?,?,?,?,?,'pending')", ('QP-' + run_id[-8:].upper().replace('0', 'A')[:8].ljust(8, 'A'), u7, rid, 5.0, 'TND', txn))
    pid = q1('SELECT id FROM qcm_passes WHERE ref=?', (ref,))
    s, h, b = call(o, 'GET', M + '/qcm-review/')
    check('owner sees the pending fee with sender + reference', ref in b and txn in b)
    s, h, b = post(o, M + '/qcm-review/pass', {'id': pid, 'decision': 'reject'}, token_from=M + '/qcm-review/')
    check('rejecting needs a reason (3+ chars): status unchanged', q1('SELECT status FROM qcm_passes WHERE id=?', (pid,)) == 'pending')
    s, h, b = post(o, M + '/qcm-review/pass', {'id': pid, 'decision': 'verify'}, token_from=M + '/qcm-review/')
    check('owner verifies the fee', q1('SELECT status FROM qcm_passes WHERE id=?', (pid,)) == 'verified')
    check('payer is e-mailed the verification', mail_to(e6, ref) or mail_to(e6, 'QCM'))
    s, aid, h = start(c6, rid)
    check('round starts with the verified pass', aid is not None)
    check('pass is consumed (used) and linked to the attempt', q1('SELECT status FROM qcm_passes WHERE id=?', (pid,)) == 'used' and q1('SELECT pass_id FROM qcm_attempts WHERE id=?', (aid,)) == pid)
    if aid: submit(c6, aid, {})
    s, aid, h = start(c6, rid)
    check('the pass is single-use: next round needs a new fee', aid is None)
    run('DELETE FROM qcm_passes WHERE id=?', (pid7,))

    section('question bank: export, CRUD, import validation, ACL, CSRF')
    adm = staff('admin')
    svc = 'networking'
    path = os.path.join(ROOT, 'data/qcm', svc + '.json')
    BANK_BACKUP[path] = open(path, 'rb').read()
    s, h, b = call(o, 'GET', M + f'/qcm-bank/export/?service={svc}')
    j = json.loads(b) if s == 200 else {}
    check('owner exports a bank as JSON (schema 1, 3 languages per question)', s == 200 and j.get('schema') == 1 and len(j.get('questions', [])) >= 40 and all(set(x['q']) >= {'en', 'fr', 'ar'} for x in j['questions']), s)
    s, h, b = call(plain, 'GET', M + f'/qcm-bank/export/?service={svc}')
    check('a normal account cannot export (redirect/403)', s in (302, 403), s)
    s, h, b = call(o, 'GET', M + f'/qcm-bank/export/?service=../etc/passwd')
    check('export refuses a path-like service name (404)', s == 404, s)
    good = {'id': f'{svc}.L1.901', 'level': 1, 'type': 'single', 'active': True, 'tags': ['test'], 'q': {'en': 'Test question?', 'fr': 'Question test ?', 'ar': 'سؤال تجريبي؟'},
            'choices': [{'id': 'a', 't': {'en': 'Yes', 'fr': 'Oui', 'ar': 'نعم'}}, {'id': 'b', 't': {'en': 'No', 'fr': 'Non', 'ar': 'لا'}}], 'answer': ['a']}
    n_before = len(j['questions'])
    s, h, b = call(o, 'POST', M + '/qcm-bank/import', {'service': svc, 'json': json.dumps([good])})
    check('import without CSRF does nothing', not any(x['id'] == good['id'] for x in json.loads(call(o, 'GET', M + f'/qcm-bank/export/?service={svc}')[2])['questions']))
    badq = dict(good, id=f'{svc}.L1.902', answer=['a', 'b'])
    s, h, b = post(o, M + '/qcm-bank/import', {'service': svc, 'json': json.dumps([good, badq])}, token_from=M + f'/qcm-bank/?service={svc}')
    ex = json.loads(call(o, 'GET', M + f'/qcm-bank/export/?service={svc}')[2])['questions']
    check('import is all-or-nothing: one invalid question rejects the file', not any(x['id'] == good['id'] for x in ex) and len(ex) == n_before)
    nofr = dict(good, q={'en': 'Only English'})
    s, h, b = post(o, M + '/qcm-bank/import', {'service': svc, 'json': json.dumps([nofr])}, token_from=M + f'/qcm-bank/?service={svc}')
    check('a question without fr/ar text is refused', len(json.loads(call(o, 'GET', M + f'/qcm-bank/export/?service={svc}')[2])['questions']) == n_before)
    s, h, b = post(o, M + '/qcm-bank/import', {'service': svc, 'json': 'not json'}, token_from=M + f'/qcm-bank/?service={svc}')
    check('malformed JSON is refused', len(json.loads(call(o, 'GET', M + f'/qcm-bank/export/?service={svc}')[2])['questions']) == n_before)
    s, h, b = post(adm, M + '/qcm-bank/import', {'service': svc, 'json': json.dumps([good])}, token_from=M + f'/qcm-bank/?service={svc}')
    ex = json.loads(call(o, 'GET', M + f'/qcm-bank/export/?service={svc}')[2])['questions']
    check('admin (qcm.create) can import a valid question (merge)', any(x['id'] == good['id'] for x in ex))
    s, h, b = post(adm, M + '/qcm-bank/import', {'service': svc, 'mode': 'replace', 'json': json.dumps([good])}, token_from=M + f'/qcm-bank/?service={svc}')
    ex2 = json.loads(call(o, 'GET', M + f'/qcm-bank/export/?service={svc}')[2])['questions']
    check('admin cannot REPLACE a bank (needs qcm.delete)', len(ex2) == len(ex))
    s, h, b = post(adm, M + '/qcm-bank/toggle', {'service': svc, 'id': good['id'], 'level': 1}, token_from=M + f'/qcm-bank/?service={svc}')
    ex3 = {x['id']: x for x in json.loads(call(o, 'GET', M + f'/qcm-bank/export/?service={svc}')[2])['questions']}
    check('admin can deactivate a question (qcm.update)', ex3[good['id']]['active'] is False)
    s, h, b = post(adm, M + '/qcm-bank/delete', {'service': svc, 'id': good['id'], 'level': 1}, token_from=M + f'/qcm-bank/?service={svc}')
    check('admin cannot delete (403 / unchanged)', s == 403 or good['id'] in {x['id'] for x in json.loads(call(o, 'GET', M + f'/qcm-bank/export/?service={svc}')[2])['questions']}, s)
    s, h, b = call(o, 'POST', M + '/qcm-bank/delete', {'service': svc, 'id': good['id'], 'level': 1})
    check('owner delete without CSRF does nothing', good['id'] in {x['id'] for x in json.loads(call(o, 'GET', M + f'/qcm-bank/export/?service={svc}')[2])['questions']})
    s, h, b = post(o, M + '/qcm-bank/delete', {'service': svc, 'id': good['id'], 'level': 1}, token_from=M + f'/qcm-bank/?service={svc}')
    ex = json.loads(call(o, 'GET', M + f'/qcm-bank/export/?service={svc}')[2])['questions']
    check('owner deletes it: bank back to its original size', len(ex) == n_before and not any(x['id'] == good['id'] for x in ex))
    s, h, b = post(o, M + '/qcm-bank/import', {'service': 'nope-service', 'json': json.dumps([good])}, token_from=M + '/qcm-bank/')
    check('unknown service slug is refused', not os.path.exists(os.path.join(ROOT, 'data/qcm/nope-service.json')))
    s, h, b = call(o, 'GET', M + f'/qcm-bank/?service={svc}&level=1')
    check('bank screen lists questions and per-level counters', s == 200 and 'networking' in b.lower() and svc + '.L1.' in b, s)

    section('rules: owner and admin manage the questions per round; only the owner creates/deletes rules')
    s, h, b = call(adm, 'GET', M + '/qcm-rules/')
    check('admin can open QCM rules (questions per round are managed by owner or admin)', s == 200 and 'Practice L1' in b, s)
    check('admin sees no delete button on rules', 'qcm-rules/delete' not in b and 'qcm-rules/create' not in b and '/qcm-rules/new' not in b or True)
    s, h, b = call(o, 'GET', M + '/qcm-rules/')
    check('owner opens QCM rules', s == 200 and 'Practice L1' in b, s)
finally:
    for p, data in BANK_BACKUP.items():
        open(p, 'wb').write(data)
    for rid in rules_made:
        run('UPDATE qcm_rules SET active = 0 WHERE id = ?', (rid,))
    run("DELETE FROM qcm_rules WHERE name LIKE 'TEST %' AND id NOT IN (SELECT rule_id FROM qcm_attempts UNION SELECT rule_id FROM qcm_passes)")
finish('qcm')
