#!/usr/bin/env python3
"""Card images: enumerate every card element, assign a stable global image index, draw an indexed
placeholder for each image and write docs/card-image-prompts.md (one prompt per index).

  python3 tools/gen/card_images.py           # (re)generate manifest, placeholders, prompts MD
  python3 tools/gen/card_images.py --check   # exit 1 when the manifest is out of date (used by tests/cards.py)

Indices are stable: a card key that already exists in data/cards/manifest.json keeps its indices; new
cards get the next free index. Replace a placeholder by dropping a real image with the same file name
(htdocs/assets/_base/images/cards/img-NNNN.webp, 1280x720 recommended, 16:9).
"""
import glob, json, os, re, sys
from PIL import Image, ImageDraw, ImageFont, ImageFilter

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
CONTENT = f'{ROOT}/data/content'
MANIFEST = f'{ROOT}/data/cards/manifest.json'
OUT = f'{ROOT}/htdocs/assets/_base/images/cards'
MD = f'{ROOT}/docs/card-image-prompts.md'
LOGO = f'{ROOT}/htdocs/assets/_base/images/logo-240.png'
B = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'
R = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'
W, H = 640, 360

PALETTE = {
    'calm': ('#5b21b6', '#4338ca'), 'warm': ('#7c3aed', '#ec4899'), 'focus': ('#6366f1', '#06b6d4'),
    'peak': ('#7c3aed', '#06b6d4'), 'cultures': ('#ec4899', '#8b5cf6'), 'built': ('#06b6d4', '#6366f1'),
    'sciences': ('#6366f1', '#22d3ee'), 'governance': ('#8b5cf6', '#4f46e5'), 'technology': ('#22d3ee', '#8b5cf6'),
}
ROT = ['calm', 'warm', 'focus', 'peak', 'cultures', 'built', 'sciences', 'governance', 'technology']

STYLE = ('Flat editorial illustration with soft depth, rounded geometric shapes, a palette of violet, indigo, '
         'cyan and a touch of pink on a deep ink-violet or light lavender ground, subtle grid texture, generous '
         'negative space, calm and honest mood. No text, no letters, no logos, no watermarks, no recognisable real '
         'people, no money piles, no promise of profit. 16:9, 1280x720.')
KIND_SCENE = {
    'cards': 'an editorial scene that explains the idea of the card in one clear visual metaphor',
    'steps': 'one stage of a process shown as a tangible step, with a path of light leading on to the next stage',
    'pipeline': 'one stage of a delivery pipeline, tools and artefacts on a clean workbench, a checkpoint marker',
    'roster': 'a role at work: a stylised silhouette (no face) with the tools of that role around it',
    'team': 'an empty seat at a long workbench, softly lit, inviting without promising anything',
    'desks': 'a service desk with a friendly neutral silhouette, a mail and phone symbol, orderly and calm',
    'macro': 'a landmark scene for the macro work, built from symbolic objects of its field',
    'project': 'a project dossier scene: a map, a timeline strip and the main deliverable as an object',
    'service': 'the main deliverable of the service as a simple object on a tidy desk, one expert silhouette (no face)',
    'path': 'a signpost-like scene showing where this path leads, a single lit trail',
}
FRAMES = ['wide establishing view', 'close-up detail of the work in progress', 'the finished deliverable being reviewed']
PATHS = {'invest': ('Invest', 'Take a part today and follow how it is used'),
         'learn': ('Learn', 'Check eligibility and start the supervised path'),
         'manage': ('Manage', 'Lead a team of experts as a manager'),
         'client': ('Client', 'Commission work from W5D'),
         'status': ('Status', 'Read the dated public status'),
         'help': ('Help', 'Answers to common questions'),
         'account': ('Account', 'Open your account or dashboard')}
CAROUSEL = {'macro': 3, 'project': 2}   # images per card for these kinds; everything else has 1


def en(v):
    if isinstance(v, dict): v = v.get('en', '')
    v = re.sub(r'\{\{[^}]*\}\}', '', str(v or ''))
    v = v.replace('**', '').replace('  ', ' ')
    return re.sub(r'\s+', ' ', v).strip(' ,;:')


def load(p):
    with open(p, encoding='utf8') as f: return json.load(f)


def enumerate_cards():
    """Ordered list of dicts: key, kind, page, title, body, tone."""
    out = []
    def add(key, kind, page, title, body, tone=None):
        out.append(dict(key=key, kind=kind, page=page, title=en(title), body=en(body)[:220], tone=tone))
    for f in sorted(glob.glob(f'{CONTENT}/pages/*.json')):
        d = load(f); slug = d.get('slug', os.path.basename(f)[:-5])
        for s in d.get('sections', []):
            t, sid = s.get('type'), s.get('id')
            if t in ('cards', 'steps', 'pipeline', 'roster'):
                for i, it in enumerate(s.get('items', [])):
                    add(f'{t}:{sid}:{i}', t, slug, it.get('title'), it.get('body'), it.get('gradient'))
            elif t == 'team':
                for i, it in enumerate(s.get('open', [])):
                    add(f'team:{sid}:open:{i}', 'team', slug, it.get('title'), it.get('body'))
            elif t == 'contact-desks':
                for i, it in enumerate(s.get('items', [])):
                    add(f'desks:{sid}:{i}', 'desks', slug, it.get('title'), it.get('body'))
    for p in sorted((load(f) for f in glob.glob(f'{CONTENT}/macros/*.json')), key=lambda m: m.get('order', 0)):
        add(f"macro:{p['slug']}", 'macro', 'macro-works', p['name'], p.get('tagline'), p['slug'])
    for p in sorted((load(f) for f in glob.glob(f'{CONTENT}/projects/*.json')), key=lambda m: m.get('order', 0)):
        add(f"project:{p['slug']}", 'project', 'projects', p['name'], p.get('tagline'), p.get('gradient'))
    for p in sorted((load(f) for f in glob.glob(f'{CONTENT}/services/*.json')), key=lambda m: m.get('id', 0)):
        add(f"service:{p['slug']}", 'service', 'services', p['title'], p.get('short'), p.get('macro'))
    for k, (t, b) in PATHS.items():
        add(f'path:{k}', 'path', 'quick-paths', t, b)
    return out


def build_manifest(cards):
    old = load(MANIFEST) if os.path.exists(MANIFEST) else {'next': 1, 'cards': {}}
    nxt = old['next']; res = {}
    for c in cards:
        n = CAROUSEL.get(c['kind'], 1)
        prev = old['cards'].get(c['key'], {}).get('images', [])
        imgs = []
        for k in range(n):
            if k < len(prev): idx = prev[k]['index']
            else: idx = nxt; nxt += 1
            scene = KIND_SCENE[c['kind']]
            frame = f' Frame {k + 1} of {n}: {FRAMES[k]}.' if n > 1 else ''
            ctx = f' Context: {c["body"]}.' if c['body'] else ''
            prompt = f'Illustration for the card "{c["title"]}" ({c["page"]} page): {scene}.{ctx}{frame} {STYLE}'
            imgs.append(dict(index=idx, prompt=prompt))
        res[c['key']] = dict(kind=c['kind'], page=c['page'], title=c['title'], tone=c['tone'], images=imgs)
    return {'next': nxt, 'cards': res}


def hexrgb(h): return tuple(int(h[i:i + 2], 16) for i in (1, 3, 5))


_G = {}


def grad(a, b):
    k = (a, b)
    if k not in _G:
        t = Image.linear_gradient('L').resize((W, H))            # vertical 0..255
        h = Image.linear_gradient('L').rotate(90).resize((W, H))  # horizontal
        m = Image.blend(t, h, .5)
        _G[k] = Image.composite(Image.new('RGB', (W, H), hexrgb(b)), Image.new('RGB', (W, H), hexrgb(a)), m)
    return _G[k].copy()


def draw(idx, key, c, k, n):
    tone = c['tone'] if c['tone'] in PALETTE else ROT[idx % len(ROT)]
    im = grad(*PALETTE[tone]).convert('RGBA')
    ov = Image.new('RGBA', (W, H), (0, 0, 0, 0)); d = ImageDraw.Draw(ov)
    for x in range(0, W, 40): d.line([(x, 0), (x, H)], fill=(255, 255, 255, 18))
    for y in range(0, H, 40): d.line([(0, y), (W, y)], fill=(255, 255, 255, 18))
    d.ellipse([W - 260, -120, W + 80, 220], fill=(255, 255, 255, 28))
    d.ellipse([-120, H - 150, 200, H + 120], fill=(0, 0, 0, 40))
    im = Image.alpha_composite(im, ov); d = ImageDraw.Draw(im)
    def ft(sz, bold=True): return ImageFont.truetype(B if bold else R, sz)
    d.text((28, 22), f"{c['kind'].upper()} · {c['page']}", font=ft(15), fill=(255, 255, 255, 215))
    num = f'{idx:04d}'
    d.text((W / 2, H / 2 - 18), num, font=ft(120), fill=(255, 255, 255, 245), anchor='mm')
    d.text((W / 2, H / 2 + 62), 'IMAGE INDEX', font=ft(16), fill=(255, 255, 255, 200), anchor='mm')
    title = c['title'] if len(c['title']) < 52 else c['title'][:49] + '...'
    d.rounded_rectangle([20, H - 62, W - 20, H - 18], radius=14, fill=(0, 0, 0, 90))
    d.text((34, H - 40), title, font=ft(18), fill=(255, 255, 255, 255), anchor='lm')
    if n > 1:
        d.text((W - 34, H - 40), f'{k + 1}/{n}', font=ft(18), fill=(255, 255, 255, 255), anchor='rm')
    if os.path.exists(LOGO):
        lg = Image.open(LOGO).convert('RGBA').resize((46, 46))
        tile = Image.new('RGBA', (58, 58), (0, 0, 0, 0)); td = ImageDraw.Draw(tile)
        td.rounded_rectangle([0, 0, 57, 57], radius=14, fill=(255, 255, 255, 235)); tile.alpha_composite(lg, (6, 6))
        im.alpha_composite(tile, (W - 78, 16))
    rgb = im.convert('RGB')
    rgb.save(f'{OUT}/img-{idx:04d}.webp', 'WEBP', quality=74, method=6)
    rgb.resize((320, 180), Image.LANCZOS).save(f'{OUT}/img-{idx:04d}-320.webp', 'WEBP', quality=72, method=6)


PART = 50                                   # images per prompts file: ~40 KB, small enough for any viewer or editor preview
MD_DIR = f'{ROOT}/docs/card-image-prompts'


def flat(man):
    """Every image, ordered by its stable index: (index, key, card, k, n)."""
    out = []
    for key, c in man['cards'].items():
        for k, im in enumerate(c['images']):
            out.append((im['index'], key, c, k, len(c['images']), im['prompt']))
    return sorted(out, key=lambda r: r[0])


def part_name(first):
    return f'img-{first:04d}-{first + PART - 1:04d}.md'


def write_md(man):
    rows = flat(man)
    os.makedirs(MD_DIR, exist_ok=True)
    for f in glob.glob(f'{MD_DIR}/img-*.md'): os.remove(f)
    parts = {}
    for r in rows: parts.setdefault(((r[0] - 1) // PART) * PART + 1, []).append(r)
    for first, items in parts.items():
        L = [f'# Card image prompts: IMG-{first:04d} to IMG-{first + PART - 1:04d}', '',
             f'[Back to the index](../card-image-prompts.md). {len(items)} image(s) in this file. To replace a placeholder, generate an image',
             'from its prompt and save it under the file name shown (1280x720 JPEG recommended). The index never changes when other cards are added.', '']
        for idx, key, c, k, n, prompt in items:
            car = f' · image {k + 1} of {n}' if n > 1 else ''
            L += [f'## IMG-{idx:04d} · {c["title"]}{car}', '',
                  f'- Card key: `{key}` ({c["kind"]}, {c["page"]} page)', f'- File: `htdocs/assets/_base/images/cards/img-{idx:04d}.webp`',
                  '- Prompt:', '', '```text', prompt, '```', '']
        with open(f'{MD_DIR}/{part_name(first)}', 'w', encoding='utf8') as f: f.write('\n'.join(L))
    used = {r[0] for r in rows}
    retired = [i for i in range(1, max(used) + 1) if i not in used]
    I = ['# Card image prompts', '',
         'Every card element on the site has one image (or a small carousel). The files in `htdocs/assets/_base/images/cards/` are',
         'indexed **placeholders** (`img-NNNN.webp`, 640x360). To replace one, generate an image from its prompt and save it under the same',
         'file name (1280x720 JPEG recommended). The index is stable: it never changes when other cards are added.', '',
         'The prompts are split into small files by index range so that no single document is too long to open or to preview in full.',
         'Regenerate everything (placeholders, manifest, these files) with `python3 tools/gen/card_images.py`.', '',
         f'Total: **{len(rows)}** images for **{len(man["cards"])}** cards (IMG-{min(used):04d} to IMG-{max(used):04d}).', '',
         '| Range | Images | Pages covered | File |', '|---|---|---|---|']
    for first, items in parts.items():
        pg = []
        for r in items:
            if r[2]['page'] not in pg: pg.append(r[2]['page'])
        I.append(f'| IMG-{first:04d} to IMG-{first + PART - 1:04d} | {len(items)} | {", ".join(pg)} | [{part_name(first)}](card-image-prompts/{part_name(first)}) |')
    if retired:
        def rng(v):
            o = []; st = pv = v[0]
            for x in v[1:] + [None]:
                if x is not None and x == pv + 1: pv = x; continue
                o.append(f'IMG-{st:04d}' if st == pv else f'IMG-{st:04d} to IMG-{pv:04d}'); st = pv = x
            return ', '.join(o)
        I += ['', f'Retired indices (their cards were removed from the site, so they have no prompt): {rng(retired)}.']
    with open(MD, 'w', encoding='utf8') as f: f.write('\n'.join(I) + '\n')


def check_md(man):
    """Every image index appears exactly once in the split files and no file is long enough to be cut off."""
    want = sorted(r[0] for r in flat(man)); got = []
    for f in glob.glob(f'{MD_DIR}/img-*.md'):
        t = open(f, encoding='utf8').read()
        if len(t.encode()) > 80000: return False
        got += [int(x) for x in re.findall(r'^## IMG-(\d{4}) ', t, re.M)]
    return sorted(got) == want and os.path.getsize(MD) < 20000


def main():
    cards = enumerate_cards()
    man = build_manifest(cards)
    if '--check' in sys.argv:
        cur = load(MANIFEST) if os.path.exists(MANIFEST) else {}
        ok = cur == man and check_md(man) and all(os.path.exists(f'{OUT}/img-{i["index"]:04d}.webp') for c in man['cards'].values() for i in c['images'])
        print('cards manifest up to date' if ok else 'cards manifest OUT OF DATE: run python3 tools/gen/card_images.py')
        sys.exit(0 if ok else 1)
    os.makedirs(OUT, exist_ok=True); os.makedirs(os.path.dirname(MANIFEST), exist_ok=True)
    for key, c in man['cards'].items():
        for k, im in enumerate(c['images']):
            draw(im['index'], key, c, k, len(c['images']))
    with open(MANIFEST, 'w', encoding='utf8') as f: json.dump(man, f, ensure_ascii=False, indent=1)
    write_md(man)
    print(f'{len(man["cards"])} cards, {man["next"] - 1} images -> {OUT}')


if __name__ == '__main__':
    main()
