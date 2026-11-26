#!/usr/bin/env python3
"""Share links carry the affiliate ?ref= code, but only for subscribers who paid for at least `affiliate.share_min_parts` part(s).

Covers: guest, signed-in without order, unpaid order, paid order without / pending / suspended / active affiliate profile,
every network href + native + copy + URL view, minimum-parts variable, programme switch, canonical/OG tags untouched,
and the query handling of Affiliate::tagUrl() (existing query, fragment, existing ref).
Usage: python3 tests/share_ref.py [base_url]   (dev/demo DB, MAIL_DRIVER=log). Test rows are removed at the end.
"""
import os, re, subprocess, sys, urllib.parse, html
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _prog import *
from _acct import _client

made_users, made_inv = [], []


def override(path, value):
    run('INSERT OR REPLACE INTO variable_overrides (path,value,changed_by) VALUES (?,?,?)', (path, str(value), 'tests/share_ref.py'))


def clear_overrides():
    run("DELETE FROM variable_overrides WHERE changed_by = 'tests/share_ref.py'")


def mk_aff(uid, status):
    import secrets
    code = 'SR' + ''.join(secrets.choice('ABCDEFGHJKLMNPQRSTUVWXYZ23456789') for _ in range(6))   # random: a truncated timestamp collided when two codes were made close together
    run('INSERT INTO affiliates (country_id,user_id,code,status,pitch,payout_gsm) VALUES (?,?,?,?,?,?)', ('tn', uid, code, status, 'test', ''))
    return code


def mk_order(email, status, parts=1, days=1):
    inv = q1('SELECT id FROM investors WHERE lower(email)=lower(?)', (email,))
    if not inv:
        inv = run('INSERT INTO investors (country_id,email,full_name) VALUES (?,?,?)', ('tn', email, 'Share Tester'))
        made_inv.append(inv)
    run('INSERT INTO investor_orders (country_id,ref,investor_id,cadence,parts_per_day,days,start_on,unit_price,total,p,t,payment_mode,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
        ('tn', 'SR' + uniq(), inv, 'daily', parts, days, '2030-01-01', 1.0, float(parts * days), 1.0, 1.0, 'manual', status))


def share(c, path='/'):
    s, h, b = call(c, 'GET', path)
    view = re.search(r'data-share-url-view>([^<]*)<', b)
    native = re.search(r'data-share-native data-share-url="([^"]*)"', b)
    copy = re.search(r'data-share-copy="([^"]*)"', b)
    nets = [html.unescape(m) for m in re.findall(r'<a class="share-item" href="([^"]*)"', b)]
    canon = re.search(r'<link rel="canonical" href="([^"]*)"', b)
    og = re.search(r'<meta property="og:url" content="([^"]*)"', b)
    return dict(status=s, body=b, view=html.unescape(view.group(1)) if view else None, native=html.unescape(native.group(1)) if native else None,
                copy=html.unescape(copy.group(1)) if copy else None, nets=nets, canon=canon.group(1) if canon else '', og=og.group(1) if og else '')


def clean(r):
    return 'ref=' not in (r['view'] or '') and 'ref=' not in (r['native'] or '') and 'ref=' not in (r['copy'] or '') and not any('ref%3D' in n or 'ref=' in n for n in r['nets'])


def tagged(r, code):
    enc = urllib.parse.quote('ref=' + code, safe='')
    return (f'ref={code}' in (r['view'] or '') and f'ref={code}' in (r['native'] or '') and f'ref={code}' in (r['copy'] or '')
            and len(r['nets']) >= 5 and all(enc in n for n in r['nets']))


try:
    reset_rl()
    clear_overrides()
    guest = _client()
    g = share(guest)
    check('guest: share panel renders with URL view, native, copy and networks', g['status'] == 200 and g['view'] and g['native'] and g['copy'] and len(g['nets']) >= 5, (g['status'], len(g['nets'])))
    check('guest: no ref anywhere in the share links', clean(g))

    c, email, uid = new_user('user', 'Share Tester', 'sh')
    made_users.append(uid)
    check('signed in, no profile, no order: no ref', clean(share(c)))

    code = mk_aff(uid, 'pending')
    mk_order(email, 'paid')
    check('paid order but affiliate profile still pending: no ref', clean(share(c)))
    run("UPDATE affiliates SET status='suspended' WHERE user_id=?", (uid,))
    check('paid order but affiliate suspended: no ref', clean(share(c)))
    run("UPDATE affiliates SET status='active' WHERE user_id=?", (uid,))

    # Reset: remove paid order, leave only unpaid ones
    run("DELETE FROM investor_orders WHERE investor_id IN (SELECT id FROM investors WHERE lower(email)=lower(?))", (email,))
    for st in ('awaiting_payment', 'pledged'):
        mk_order(email, st, parts=3, days=2)
    check('active affiliate but only unpaid / pledged orders: no ref', clean(share(c)))

    mk_order(email, 'paid', parts=1, days=1)
    r = share(c)
    check('active affiliate + 1 paid part: ref on the URL view, native and copy buttons', tagged(r, code), (r['view'], r['native'], r['copy']))
    check('every network href (WhatsApp, Facebook, X, LinkedIn, Telegram, Reddit, e-mail) carries the encoded ref', all(urllib.parse.quote('ref=' + code, safe='') in n for n in r['nets']) and len(r['nets']) >= 7, r['nets'])
    check('the tagged URL is the site URL + ?ref=CODE (no double ?)', (r['view'] or '').count('?') == 1 and (r['view'] or '').endswith('?ref=' + code), r['view'])
    check('canonical and og:url are untouched (no ref)', 'ref=' not in r['canon'] and 'ref=' not in r['og'] and r['canon'] != '')

    r2 = share(c, '/services/')
    check('another page also carries the ref', r2['status'] == 200 and tagged(r2, code), r2['view'])

    override('affiliate.share_min_parts', 5)
    check('share_min_parts = 5 with 1 paid part: no ref', clean(share(c)))
    mk_order(email, 'paid', parts=2, days=2)  # +4 parts -> 5 in total
    check('share_min_parts = 5 reached (1 + 2x2 paid parts): ref is back', tagged(share(c), code))
    override('affiliate.share_min_parts', 0)
    check('share_min_parts = 0 is treated as 1 (never "everybody")', tagged(share(c), code) and clean(share(guest)))
    clear_overrides()

    override('affiliate.enabled', 0)
    check('programme paused: no ref', clean(share(c)))
    clear_overrides()

    # Another subscriber with a paid order must not see someone else's code
    c2, email2, uid2 = new_user('user', 'Other Tester', 'sh')
    made_users.append(uid2)
    code2 = mk_aff(uid2, 'active')
    check('subscriber with an active profile but no order: no ref', clean(share(c2)))
    mk_order(email2, 'paid')
    r = share(c2)
    check('second subscriber gets their own code, not the first one', tagged(r, code2) and code not in (r['view'] or ''), r['view'])

    # case-insensitive e-mail match between users.email and investors.email
    c3, email3, uid3 = new_user('user', 'Case Tester', 'sh')
    made_users.append(uid3)
    code3 = mk_aff(uid3, 'active')
    mk_order(email3.upper(), 'paid')
    check('order e-mail in another letter case still matches the account', tagged(share(c3), code3))

    section('Affiliate::tagUrl query handling (PHP)')
    php = lambda expr: subprocess.run(['php', '-r', 'require "bin/_boot.php"; echo ' + expr + ';'], cwd=ROOT, capture_output=True, text=True).stdout.strip()
    check('plain URL gets ?ref=', php('W5D\\Affiliate::tagUrl("https://x.test/a/","ABC23456")') == 'https://x.test/a/?ref=ABC23456')
    check('existing query gets &ref=', php('W5D\\Affiliate::tagUrl("https://x.test/a/?q=1","ABC23456")') == 'https://x.test/a/?q=1&ref=ABC23456')
    check('fragment is kept after the ref', php('W5D\\Affiliate::tagUrl("https://x.test/a/#top","ABC23456")') == 'https://x.test/a/?ref=ABC23456#top')
    check('an existing ref is never duplicated or replaced', php('W5D\\Affiliate::tagUrl("https://x.test/a/?ref=OLD12345","ABC23456")') == 'https://x.test/a/?ref=OLD12345')
    check('no code (null) leaves the URL as it is (CLI has no session)', php('W5D\\Affiliate::tagUrl("https://x.test/a/")') == 'https://x.test/a/')
finally:
    clear_overrides()
    for u in made_users:
        run('DELETE FROM affiliates WHERE user_id=?', (u,))
    for i in made_inv:
        run('DELETE FROM investor_orders WHERE investor_id=?', (i,))
        run('DELETE FROM investors WHERE id=?', (i,))
    reset_rl()
finish('share_ref')
