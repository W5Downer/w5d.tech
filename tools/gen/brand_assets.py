#!/usr/bin/env python3
"""Generates brand icons + 1200x630 social placeholder images (alias.jpg) and docs/social-share-image-prompts.md.
Placeholders are Latin-only (safe fonts). Replace any og-*.jpg with the AI-generated artwork from the prompts doc (same file name)."""
import os, math, re
from PIL import Image, ImageDraw, ImageFont, ImageFilter
ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
IMG = f'{ROOT}/htdocs/assets/_base/images'
SOC = f'{IMG}/social'
os.makedirs(SOC, exist_ok=True)
B = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'
R = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'
def font(sz, bold=True): return ImageFont.truetype(B if bold else R, sz)
INK = (7, 7, 17)
G = {  # gradient pairs
 'peak': ((124, 58, 237), (6, 182, 212)), 'focus': ((99, 102, 241), (6, 182, 212)), 'warm': ((124, 58, 237), (236, 72, 153)),
 'cultures': ((236, 72, 153), (139, 92, 246)), 'built': ((6, 182, 212), (99, 102, 241)), 'sciences': ((99, 102, 241), (34, 211, 238)),
 'governance': ((139, 92, 246), (79, 70, 229)), 'technology': ((124, 58, 237), (59, 130, 246)), 'calm': ((91, 33, 182), (67, 56, 202)),
}
def grad(w, h, a, b):
    im = Image.new('RGB', (w, h)); px = im.load()
    for y in range(h):
        for x in range(w):
            t = (x / w * .6 + y / h * .4); px[x, y] = tuple(int(a[i] + (b[i] - a[i]) * t) for i in range(3))
    return im
def rr_mask(w, h, r):
    m = Image.new('L', (w, h), 0); ImageDraw.Draw(m).rounded_rectangle([0, 0, w - 1, h - 1], r, fill=255); return m
LOGO_SVG = f'{IMG}/logo.svg'   # traced from tools/gen/source/w5d.tech-logo.jpg (the official logo supplied by the owner)
def logo_png(width, bg=None):
    """Render the vector logo to a PIL RGBA image of the given width (transparent unless bg given)."""
    import subprocess, io
    cmd = ['rsvg-convert', '-w', str(width), LOGO_SVG]
    if bg: cmd[1:1] = ['-b', bg]
    return Image.open(io.BytesIO(subprocess.run(cmd, check=True, capture_output=True).stdout)).convert('RGBA')
def icon(size, maskable=False, g='peak'):
    """App icon: the W5D infinity logo on a white tile (rounded, or full-bleed + safe zone when maskable)."""
    if maskable: out = Image.new('RGBA', (size, size), (255, 255, 255, 255))
    else:
        out = Image.new('RGBA', (size, size), (0, 0, 0, 0)); out.paste(Image.new('RGBA', (size, size), (255, 255, 255, 255)), (0, 0), rr_mask(size, size, int(size * .22)))
    lw = int(size * (.60 if maskable else .74)); lg = logo_png(lw)
    out.alpha_composite(lg, ((size - lg.width) // 2, (size - lg.height) // 2))
    return out
icon(512).save(f'{IMG}/icon-512.png'); icon(192).save(f'{IMG}/icon-192.png')
icon(512, True).convert('RGB').save(f'{IMG}/icon-maskable-512.png')
icon(180, True).convert('RGB').save(f'{IMG}/apple-touch-icon.png')
icon(32).save(f'{IMG}/favicon-32.png')
icon(256).save(f'{ROOT}/htdocs/favicon.ico', sizes=[(16, 16), (32, 32), (48, 48), (64, 64)])
# favicon.svg = white rounded tile + the logo paths (viewBox 0 0 32 32)
_svg = open(LOGO_SVG).read(); _vb = [float(v) for v in re.search(r'viewBox="([^"]+)"', _svg).group(1).split()]; _body = re.search(r'<title>W5D</title>(.*)</svg>', _svg, re.S).group(1)
_sc = 24 / _vb[2]; _tx = 4 - _vb[0] * _sc; _ty = 16 - (_vb[1] + _vb[3] / 2) * _sc
open(f'{IMG}/favicon.svg', 'w').write(f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><rect width="32" height="32" rx="7" fill="#fff"/><g transform="translate({_tx:.3f} {_ty:.3f}) scale({_sc:.5f})">{_body}</g></svg>\n')
# small PNG logos for mail (SVG is not reliable in mail clients) and the header
logo_png(480).save(f'{IMG}/logo-480.png'); logo_png(240).save(f'{IMG}/logo-240.png')

# ---- alias table: alias, grad, kicker, title, page(s), prompt scene ----
A = []
def add(alias, g, kicker, title, pages, scene): A.append(dict(alias=alias, g=g, kicker=kicker, title=title, pages=pages, scene=scene))
add('og-default', 'peak', 'W5D · World Five Dimensions', 'Five macro works. One path.', 'Fallback for any page without its own image', 'a luminous five-pointed constellation of connected nodes over a deep ink-violet background, subtle grid, cyan and violet light trails')
add('og-home', 'peak', 'W5D', 'Take a part. Fund a path.', '/', 'five glowing macro-work orbs (violet, indigo, cyan, pink, teal) orbiting one central daily-sunrise disc, deep ink background, premium fintech-meets-education feel')
add('og-investors', 'focus', 'Investors', 'A part every day', '/investors/', 'a calendar strip of thirty small glowing tiles filling up one by one, a single bright tile for today, calm confident mood, no money piles, no charts promising profit')
add('og-pricing', 'warm', 'Pricing', 'One public price function', '/pricing/', 'an elegant smooth curve rising gently to a flat cap line, one marked point labelled today, minimal axes, violet-to-cyan gradient line on ink background')
add('og-checkout', 'peak', 'Checkout', 'Take a part today', '/checkout/', 'a clean three-step wizard card (details, parts, review) floating over a soft glow, with a small smartphone showing a payment confirmation')
add('og-subscription', 'focus', 'Subscription', 'A part, every day', '/subscription/', 'a circular arrow loop around a sun icon, daily rhythm, small calendar ticks, glowing indigo')
add('og-how-it-works', 'calm', 'How it works', 'From a part to a certified path', '/how-it-works/', 'four connected stages as stepping stones across a glowing river: fund, assign, work, audit with a random 5 percent magnifier')
add('og-join', 'warm', 'Join', 'Join the path', '/join/', 'an open door of light with five silhouettes of different people walking toward it, inclusive and hopeful')
add('og-join-eligibility', 'warm', 'Join', 'Check your eligibility', '/join/eligibility/', 'a friendly checklist card with ticks and a graduation cap, soft violet glow')
add('og-app-register', 'warm', 'Register', 'Register as an applicant', '/app/register/', 'a form card with a name field and a verified badge, small laptop, cyan accents')
add('og-careers', 'focus', 'Careers', 'A path, not a job', '/careers/', 'a branching path of light splitting into five trails, with small briefcase and compass icons, honest and grounded mood')
add('og-careers-managers', 'peak', 'Managers', 'Lead a macro work', '/careers/managers/', 'a leader silhouette on a hill overlooking five glowing districts connected by bridges')
add('og-partners', 'built', 'Partners', 'Build with W5D', '/partners/', 'two interlocking rings of light, one violet one cyan, with a handshake silhouette, institutional tone')
add('og-team', 'calm', 'Team', 'One owner, one developer, open seats', '/team/', 'a long wooden workbench with one laptop and one chair lit warmly, and several empty chairs waiting in soft shadow')
add('og-about', 'calm', 'About', 'Who builds W5D', '/about/', 'a workshop table seen from above with blueprint sheets, a compass and a glowing five-sided emblem')
add('og-ontology', 'sciences', 'Ontology', 'One shared vocabulary', '/ontology/', 'a knowledge graph of labelled nodes forming a brain-like network, cyan and violet edges')
add('og-countries-tunisia', 'built', 'Tunisia', 'Rooted in Tunisia', '/countries/tunisia/', 'abstract map of Tunisia outlined by glowing dots with a Mediterranean-blue gradient, 24 governorate markers')
add('og-faq', 'calm', 'FAQ', 'Straight answers', '/faq/', 'stacked speech bubbles with question and answer marks, friendly, glowing indigo')
add('og-contact', 'focus', 'Contact', 'Write to the right desk', '/contact/', 'an envelope opening into five coloured desks icons (investors, partners, press, support, security)')
add('og-newsletter', 'warm', 'Newsletter', 'The weekly note', '/newsletter/', 'a paper plane leaving a glowing envelope trailing a gentle weekly calendar')
add('og-status', 'sciences', 'Status', 'Live programme status', '/status/', 'a calm dashboard of five green status lights and a heartbeat line on dark glass')
add('og-macro-works', 'peak', 'Macro works', 'Five domains', '/macro-works/', 'five tall luminous pillars of different colours standing in a row like a skyline, each topped with a small icon')
add('og-services', 'focus', 'Services', 'Services in five domains', '/services/', 'a dense but tidy honeycomb of small glowing hexagons grouped in five colours')
add('og-projects', 'built', 'Projects', 'Projects you can follow', '/projects/', 'a roadmap timeline with milestone flags and small prototype screens floating above')
add('og-developers', 'technology', 'Developers', 'Open API & OpenAPI', '/developers/', 'a code editor window with a JSON response and a glowing endpoint badge, dark theme, cyan syntax colours')
add('og-agents', 'technology', 'AI agents', 'Machine-readable by design', '/agents/', 'a friendly abstract robot head made of nodes reading a manifest scroll, violet and cyan')
add('og-sitemap', 'calm', 'Sitemap', 'Every page, one list', '/sitemap/', 'a tree diagram of connected page cards fanning out from a home icon')
add('og-for', 'peak', 'Audiences', 'One W5D, seven doors', '/for/', 'seven doors of light in a curved corridor, each a different colour, welcoming')
for k, t, d in [('owner', 'Owner', 'a captain at the helm of a glowing ship made of five sails'), ('admin', 'Admin', 'a control panel console with toggles, shields and a calm operator silhouette'),
                ('leader', 'Leader', 'a leader figure holding a lantern guiding a small team across a bridge'), ('member', 'Member', 'a team of people seated around a glowing table, collaborative'),
                ('investor', 'Investor', 'one bright tile added to a calendar each day, a hand placing it gently'), ('customer', 'Customer', 'a client at a counter receiving a certified work package box with a ribbon'),
                ('user', 'User', 'a curious visitor with a lantern at a crossroads with five signposts')]:
    add(f'og-for-{k}', 'peak' if k in ('owner', 'investor') else 'focus', f'For {t.lower()}s' if k != 'staff' else '', f'W5D for the {k}', f'/for/{k}/', d)
for slug, name, g, d in [('built', 'Built Environment', 'built', 'a futuristic yet humble city skyline of bridges and green buildings, cyan glow'),
                         ('cultures', 'Cultures & Humanities', 'cultures', 'an ancient arch and an open book releasing pink-violet light'),
                         ('governance', 'Governance', 'governance', 'balanced scales above a round table, violet-indigo glow'),
                         ('sciences', 'Sciences', 'sciences', 'a flask, an atom and a telescope arranged around a glowing beaker, indigo-cyan'),
                         ('technology', 'Technology', 'technology', 'a luminous chip with circuit traces fanning out like roots, violet-blue')]:
    add(f'og-macro-{slug}', g, 'Macro work', name, f'/macro-works/{slug}/', d)
add('og-service', 'focus', 'Service', 'A weekly certified package', '/services/{slug}/ (all 130 services)', 'a single glowing hexagon package box on a plinth with a ribbon marked with a week calendar')
add('og-project', 'built', 'Project', 'A published W5D project', '/projects/{slug}/ (all 25 projects)', 'a prototype screen floating above a blueprint with a status flag, cyan glow')
add('og-tunisia-governorate', 'built', 'Tunisia', 'A governorate hub', '/tn/{governorate}/', 'a single highlighted governorate outline on a dark Tunisia map with a soft pin')
add('og-trust', 'calm', 'Trust', 'Declared limits, in writing', '/trust/ and /trust/{page}/ (statement, privacy, terms, security, sampling, corrections)', 'a transparent glass shield with a checkmark and a visible 5 percent sample magnifier, serious calm mood')
add('og-account', 'calm', 'Account', 'Your W5D account', '/account/* (sign in, register, dashboard; these pages are noindex)', 'a rounded avatar badge inside a shield with a lock, soft indigo glow')

def card(a):
    W, H = 1200, 630
    bg = grad(W, H, *G[a['g']]).filter(ImageFilter.GaussianBlur(40))
    dark = Image.new('RGB', (W, H), INK); im = Image.blend(dark, bg, .55)
    ov = Image.new('RGBA', (W, H), (0, 0, 0, 0)); od = ImageDraw.Draw(ov)
    for i in range(5):  # five dimension dots
        cx = 870 + i * 62; cy = 150 + int(30 * math.sin(i * 1.3)); r = 22 + i * 3
        od.ellipse([cx - r, cy - r, cx + r, cy + r], fill=(255, 255, 255, 40 + i * 12), outline=(255, 255, 255, 120), width=2)
    im = Image.alpha_composite(im.convert('RGBA'), ov).convert('RGB'); d = ImageDraw.Draw(im)
    d.rounded_rectangle([60, 56, 256, 150], 24, fill=(255, 255, 255)); lg = logo_png(164); im.paste(lg, (60 + (196 - lg.width) // 2, 56 + (94 - lg.height) // 2), lg)
    d.text((276, 86), 'W5D', font=font(40), fill='white')
    d.text((60, 300), a['kicker'].upper(), font=font(30), fill=(196, 181, 253))
    t = a['title']; fs = 84
    while d.textlength(t, font=font(fs)) > 1080 and fs > 40: fs -= 4
    d.text((60, 350), t, font=font(fs), fill='white')
    d.text((60, 560), 'w5d.tech', font=font(30, False), fill=(226, 232, 240))
    return im
for a in A: card(a).save(f"{SOC}/{a['alias']}.jpg", quality=86, optimize=True)

# ---- prompts doc ----
STYLE = ('Style guide (applies to every image): 1200×630 px (1.91:1), JPG, safe area 60 px, deep ink background #070711, violet #7c3aed / indigo #6366f1 / cyan #06b6d4 accents, '
         'soft glow, flat-3D premium illustration, no photographs of real people, no real brand logos, no stock-market charts, no currency symbols or promised numbers, '
         'no text inside the image except where stated (text is added by the page title). Honest tone: investing in a path, not a job, no promised returns.')
L = ['# W5D social share images — prompts & aliases', '',
     'Every public page maps to an **alias** (file name) in `htdocs/assets/_base/images/social/{alias}.jpg`. The mapping lives in `config/social.php` (`images`) and `src/Social.php` (prefix rules). '
     'This repository ships **generated placeholder cards** with the same names: replace a file with your final artwork (same name, 1200×630 JPG) and nothing else changes. '
     'Pages whose alias file is missing fall back to `og-default.jpg` (`SOCIAL_DEFAULT_IMAGE` in `.env`).', '',
     '> Regenerate the placeholders (and this file) with `python3 tools/gen/brand_assets.py`. Re-running overwrites the placeholder JPGs, so keep your final artwork out of that script’s way by running it only once on a fresh clone.', '',
     '## Style guide', '', STYLE, '', '## Images', '', '| Alias file | Page(s) |', '|---|---|']
for a in A: L.append(f"| `{a['alias']}.jpg` | `{a['pages']}` |")
L += ['', '## Prompts (copy-paste, English)', '']
for a in A:
    alt = f"{a['title']} — W5D"
    L += [f"### {a['alias']}.jpg", f"- **Used on:** `{a['pages']}`", f"- **Alt text:** {alt}",
          f"- **Prompt:** Wide 1200×630 social-share illustration for the W5D website, theme “{a['title']}”: {a['scene']}. Dark ink background, violet–indigo–cyan glow, premium flat-3D style, generous empty space on the left for the page title, no text, no logos, no real people.", '']
L += ['## Other brand files', '', '| File | Size | Purpose |', '|---|---|---|', '| `images/icon-192.png`, `icon-512.png` | 192/512 | PWA manifest, Organization logo (JSON-LD) |', '| `images/icon-maskable-512.png` | 512 | Maskable PWA icon |', '| `images/apple-touch-icon.png` | 180 | iOS home screen |', '| `images/favicon.svg`, `favicon-32.png`, `/favicon.ico` | — | Browser tabs |', '']
open(f'{ROOT}/docs/social-share-image-prompts.md', 'w').write('\n'.join(L))
print(len(A), 'aliases')
