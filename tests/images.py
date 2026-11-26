"""Owner image library: drag-drop upload of pictures / ZIP -> one uniform size -> .webp (+ safety limits, ACL, CSRF).
Run against the dev server:  python3 tests/images.py   (BASE env var overrides http://127.0.0.1:8080)
Files it creates under htdocs/assets/_base/images/library/ are removed again at the end."""
import io, os, re, sys, uuid, zipfile, glob
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import urllib.request, urllib.error
from PIL import Image
from _staff import staff_client, staff_login, MOUNT, ROOT, _call, _csrf

BASE = os.environ.get('BASE', 'http://127.0.0.1:8080').rstrip('/')
LIB = os.path.join(ROOT, 'htdocs/assets/_base/images/library')
ok = bad = 0


def check(name, cond, extra=''):
    global ok, bad
    if cond: ok += 1; print('  ok  ', name)
    else: bad += 1; print('  FAIL', name, extra)


def section(t): print('\n' + t)


def png(w, h, color=(200, 40, 120), fmt='PNG'):
    b = io.BytesIO(); Image.new('RGB', (w, h), color).save(b, fmt); return b.getvalue()


def multipart(fields, files):
    bd = '----w5d' + uuid.uuid4().hex; out = b''
    for k, v in fields.items():
        out += f'--{bd}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode()
    for name, data in files:
        out += f'--{bd}\r\nContent-Disposition: form-data; name="files[]"; filename="{name}"\r\nContent-Type: application/octet-stream\r\n\r\n'.encode() + data + b'\r\n'
    out += f'--{bd}--\r\n'.encode()
    return out, 'multipart/form-data; boundary=' + bd


def post(c, path, fields, files, csrf=True):
    page = MOUNT + '/images/'
    if csrf: fields = {'_csrf': _csrf(c, BASE, page), **fields}
    body, ct = multipart(fields, files)
    req = urllib.request.Request(BASE + path, data=body, method='POST'); req.add_header('Content-Type', ct)
    try: r = c[0].open(req, timeout=60)
    except urllib.error.HTTPError as e: r = e
    return r.status if hasattr(r, 'status') else r.code, dict(r.headers), r.read().decode('utf-8', 'replace')


def lib(): return set(os.path.basename(p) for p in glob.glob(LIB + '/*.webp'))


before = lib()
section('access')
anon = staff_login.__globals__['_client']()
s, h, t = _call(anon, BASE, 'GET', MOUNT + '/images/')
check('signed-out visitor is sent to the sign-in page', s in (301, 302, 303) and 'login' in h.get('Location', ''), s)
s, h, t = post(anon, MOUNT + '/images/upload', {}, [('a.png', png(10, 10))], csrf=False)
check('signed-out upload is refused', s in (301, 302, 303) and 'login' in h.get('Location', ''), s)
adm = staff_client(BASE, 'admin', prefix='img')
s, h, t = _call(adm, BASE, 'GET', MOUNT + '/images/')
check('an admin (not the owner) gets 403 on the image library', s == 403, s)
s, h, t = post(adm, MOUNT + '/images/upload', {}, [('a.png', png(10, 10))], csrf=False)
check('an admin cannot upload either', s == 403, s)
check('nothing was written by the refused requests', lib() == before)

own = staff_client(BASE, 'owner')
s, h, t = _call(own, BASE, 'GET', MOUNT + '/images/')
check('owner sees the image library', s == 200 and 'data-dropzone' in t and 'name="files[]"' in t and 'enctype="multipart/form-data"' in t, s)
check('drop zone is a labelled, keyboard-focusable control', 'tabindex="0"' in t and 'for="img-files"' in t)
check('size presets and quality fields are present', 'data-preset="1280x720"' in t and 'name="quality"' in t and 'name="fit"' in t)
check('no inline handler (CSP): no onfocus/onclick in the page', not re.search(r'\son(focus|click|change|drop)=', t))
check('limits are stated to the owner', 'per picture' in t and 'per upload' in t)

section('CSRF')
s, h, t = post(own, MOUNT + '/images/upload', {'w': 100, 'h': 100}, [('csrf.png', png(50, 50))], csrf=False)
check('upload without a CSRF token saves nothing', lib() == before)
s, h, t = _call(own, BASE, 'POST', MOUNT + '/images/delete', {'file': 'x.webp'})
check('delete without a CSRF token is rejected', s in (301, 302, 303))

section('single pictures: one uniform size, .webp')
files = [('Holiday Photo.PNG', png(1600, 900)), ('Tall one!.jpg', png(300, 900, fmt='JPEG')), ('tiny.gif', png(40, 30, fmt='GIF')), ('already.webp', png(700, 700, fmt='WEBP'))]
s, h, t = post(own, MOUNT + '/images/upload', {'w': 640, 'h': 360, 'fit': 'cover', 'quality': 80}, files)
new = sorted(lib() - before)
check('four pictures produce four .webp files', len(new) == 4 and all(f.endswith('.webp') for f in new), new)
sizes = set(); okwebp = True
for f in new:
    p = os.path.join(LIB, f); head = open(p, 'rb').read(12)
    okwebp &= head[:4] == b'RIFF' and head[8:12] == b'WEBP'
    sizes.add(Image.open(p).size)
check('every output is a real WebP file', okwebp)
check('every output has exactly the same size 640x360', sizes == {(640, 360)}, sizes)
check('file names are safe slugs (no spaces, capitals or punctuation)', all(re.fullmatch(r'[a-z0-9-]+\.webp', f) for f in new), new)
s, h, t = _call(own, BASE, 'GET', MOUNT + '/images/')
check('the library lists them with their address and size', all(f in t for f in new) and '640×360' in t and '/assets/_base/images/library/' in t)
check('result report shows saved sizes', 'Last upload' in t or 'saved' in t)
s, h, body = _call(own, BASE, 'GET', '/assets/_base/images/library/' + new[0])
check('a stored picture is served publicly as a file', s == 200)

section('same name twice -> unique name, nothing overwritten')
s, h, t = post(own, MOUNT + '/images/upload', {'w': 640, 'h': 360}, [('Holiday Photo.PNG', png(800, 800, (1, 2, 3)))])
again = sorted(lib() - before - set(new))
check('a second "Holiday Photo.PNG" gets a numbered name', len(again) == 1 and re.fullmatch(r'holiday-photo-\d+\.webp', again[0]), again)

section('contain keeps the whole picture')
s, h, t = post(own, MOUNT + '/images/upload', {'w': 400, 'h': 400, 'fit': 'contain'}, [('wide-bar.png', png(800, 200, (255, 0, 0)))])
f = sorted(lib() - before - set(new) - set(again))
im = Image.open(os.path.join(LIB, f[0])).convert('RGBA') if f else None
check('contain output is the requested 400x400', im is not None and im.size == (400, 400))
check('contain leaves transparent bars top and bottom', im is not None and im.getpixel((200, 5))[3] == 0 and im.getpixel((200, 200))[3] > 200)

section('ZIP of pictures')
zb = io.BytesIO()
with zipfile.ZipFile(zb, 'w') as z:
    z.writestr('set/one.png', png(500, 500)); z.writestr('set/two.jpg', png(900, 300, fmt='JPEG'))
    z.writestr('../../evil.png', png(50, 50)); z.writestr('/abs/path.png', png(50, 50))
    z.writestr('notes.txt', b'hello'); z.writestr('shell.php', b'<?php echo 1;'); z.writestr('__MACOSX/._one.png', b'junk')
    z.writestr('fake.png', b'this is not a picture at all')
before2 = lib()
s, h, t = post(own, MOUNT + '/images/upload', {'w': 320, 'h': 180, 'fit': 'cover'}, [('bundle.zip', zb.getvalue())])
zn = sorted(lib() - before2)
check('ZIP: only real pictures are converted (one, two, evil, path)', len(zn) == 4, zn)
check('ZIP: all converted pictures share one size', {Image.open(os.path.join(LIB, f)).size for f in zn} == {(320, 180)})
check('ZIP: path tricks wrote nothing outside the library', not os.path.exists(os.path.join(ROOT, 'evil.png')) and not os.path.exists('/abs/path.png') and not os.path.exists(os.path.join(ROOT, 'htdocs/assets/evil.png')))
check('ZIP: no .php or .txt file ever lands in the library', not [p for p in os.listdir(LIB) if not p.endswith('.webp') and p != '.gitkeep'])
s, h, t = _call(own, BASE, 'GET', MOUNT + '/images/')
check('ZIP: skipped entries are reported with a reason', 'notes.txt' in t and 'shell.php' in t and 'fake.png' in t and 'skipped' in t)

section('hostile or broken input')
before3 = lib()
s, h, t = post(own, MOUNT + '/images/upload', {'w': 100, 'h': 100}, [('payload.png', b'<?php system($_GET[1]); ?>'), ('empty.png', b''), ('page.html', b'<script>1</script>')])
check('text/PHP disguised as a picture, an empty file and an .html file are all refused', lib() == before3)
s, h, t = _call(own, BASE, 'GET', MOUNT + '/images/')
check('the refusal reasons are shown', 'payload.png' in t and 'page.html' in t)
big = png(5000, 4000, (9, 9, 9))   # 20 megapixels > limit, tiny when compressed
s, h, t = post(own, MOUNT + '/images/upload', {'w': 100, 'h': 100}, [('bomb.png', big)])
check('a picture with too many pixels is refused (decompression guard)', lib() == before3)
s, h, t = _call(own, BASE, 'GET', MOUNT + '/images/')
check('...and the reason mentions pixels', 'pixels' in t.lower())
s, h, t = post(own, MOUNT + '/images/upload', {'w': 100, 'h': 100}, [])
check('no file chosen -> friendly error, no crash', s in (301, 302, 303) and lib() == before3)
s, h, t = post(own, MOUNT + '/images/upload', {'w': 999999, 'h': -5}, [('clamp.png', png(100, 100))])
cl = sorted(lib() - before3)
check('absurd sizes are clamped to the allowed range', len(cl) == 1 and Image.open(os.path.join(LIB, cl[0])).size == (4096, 64), [Image.open(os.path.join(LIB, f)).size for f in cl])

section('delete')
victim = sorted(lib() - before)[0]
s, h, t = post(own, MOUNT + '/images/delete', {'file': victim}, [])
check('owner can delete a library picture', victim not in lib())
keep = lib()
for bad_name in ['../../../.env', '..%2f..%2fREADME.md', 'logo.svg', 'nope.webp', 'a/b.webp', '.htaccess']:
    post(own, MOUNT + '/images/delete', {'file': bad_name}, [])
check('path-traversal / foreign names delete nothing', lib() == keep and os.path.exists(os.path.join(ROOT, '.env')) and os.path.exists(os.path.join(ROOT, 'README.md')))
s, h, t = _call(own, BASE, 'GET', MOUNT + '/audit/')
check('uploads and deletes are in the audit log', 'images.upload' in t and 'images.delete' in t)

section('cleanup + system check')
for f in lib() - before:
    os.remove(os.path.join(LIB, f))
check('test pictures removed', lib() == before)
s, h, t = _call(own, BASE, 'GET', MOUNT + '/system/')
check('System screen reports gd/WebP and zip readiness', 'Image library' in t and 'gd+WebP ready' in t)

print(f'\nimages: {ok} passed, {bad} failed')
sys.exit(1 if bad else 0)
