#!/usr/bin/env python3
"""Google OAuth branding / API Services User Data Policy guard. Usage: python3 tests/google.py [base_url]
Keeps true what the home page, privacy policy and terms tell Google's reviewers (and users):
  - the home page states the site's purpose, the Google data use (openid/email/profile only), Limited Use, no AI-training, no NCII, in en/fr/ar;
  - the privacy policy and terms carry the Google and content-rule sections; footer links to both are on every page;
  - the code really requests only openid email profile, keeps no avatar, keeps no Google token;
  - the Google button shows its data note ONLY when Google is configured (checked on a throw-away server with fake credentials)."""
import os, re, subprocess, sys, time, urllib.request, urllib.error, http.cookiejar, socket
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
BASE = next((a for a in sys.argv[1:] if a.startswith('http')), 'http://127.0.0.1:8080').rstrip('/')
ok = bad = 0
def check(n, c, extra=''):
    global ok, bad
    if c: ok += 1
    else: bad += 1; print('  FAIL', n, extra)

def fetch(base, path, lang='en'):
    jar = http.cookiejar.CookieJar()
    op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
    op.open(base + f'/language/{lang}?r=/', timeout=20).read()
    try:
        r = op.open(base + path, timeout=20); return r.status, r.read().decode('utf-8', 'replace')
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8', 'replace')
def main_text(h):
    m = re.search(r'<main.*?</main>', h, re.S); t = re.sub(r'<[^>]+>', ' ', m.group(0) if m else h)
    return re.sub(r'\s+', ' ', t.replace('&amp;', '&').replace('&#039;', "'").replace('&quot;', '"'))
POLICY = 'https://developers.google.com/terms/api-services-user-data-policy'
MUST = {
    'en': ['purpose', 'optional', 'openid', 'email', 'profile', 'Limited Use', 'advertis', 'artificial-intelligence', 'non-consensual intimate imagery', 'AI-generated'],
    'fr': ['facultative', 'openid', 'email', 'profile', 'utilisation limitée', 'publicité', 'intelligence artificielle', 'images intimes non consenties', 'IA'],
    'ar': ['اختياري', 'openid', 'email', 'profile', 'الاستخدام المحدود', 'الإعلان', 'الذكاء الاصطناعي', 'غير الرضائية'],
}
for lang in ('en', 'fr', 'ar'):
    s, h = fetch(BASE, '/', lang)
    check(f'[{lang}] home 200', s == 200)
    m = re.search(r'<section id="home-purpose".*?</section>', h, re.S)
    check(f'[{lang}] home has the purpose section', bool(m))
    sec = m.group(0) if m else ''
    t = main_text(sec)
    check(f'[{lang}] home links the Google API Services User Data Policy', POLICY in sec)
    check(f'[{lang}] home links privacy + terms', '/trust/privacy/' in sec and '/trust/terms/' in sec)
    for w in MUST[lang]:
        check(f'[{lang}] home says "{w}"', w.lower() in t.lower(), t[:80])
    check(f'[{lang}] home section has no unresolved tokens', '{{' not in sec and '}}' not in sec)
    check(f'[{lang}] purpose section is visible text, not hidden', 'hidden' not in (re.match(r'<section[^>]*>', sec).group(0) if sec else 'hidden') and 'display:none' not in sec)
    check(f'[{lang}] home has exactly one h1', len(re.findall(r'<h1[ >]', h)) == 1)
    check(f'[{lang}] home footer links privacy + terms', h.count('/trust/privacy/') >= 2 and h.count('/trust/terms/') >= 2)
    first = h.find('home-purpose'); check(f'[{lang}] purpose section is on the home page, after the hero and the carousel, not at the top', first > 0 and len(re.findall(r'<section ', h[:first])) >= 4, str(len(re.findall(r'<section ', h[:first]))))
    # privacy + terms
    s, p = fetch(BASE, '/trust/privacy/', lang)
    check(f'[{lang}] privacy has the Google section', 'id="pv-google"' in p and POLICY in p)
    pt = main_text(p)
    check(f'[{lang}] privacy names the scopes', all(x in pt for x in ('openid', 'email', 'profile')))
    s, tm = fetch(BASE, '/trust/terms/', lang)
    check(f'[{lang}] terms have the content rules (NCII)', 'id="tm-content"' in tm and 'NCII' in tm)

# ---- the code matches the words
oauth = open(os.path.join(ROOT, 'config/oauth.php'), encoding='utf-8').read()
g = re.search(r"'google'\s*=>\s*\[.*?\n    \],", oauth, re.S).group(0)
check("google scope is exactly 'openid email profile'", re.search(r"'scope'\s*=>\s*'openid email profile'", g) is not None)
check('google asks for no offline access (no refresh token)', "access_type' => 'online'" in open(os.path.join(ROOT, 'src/OAuth.php')).read())
acc = open(os.path.join(ROOT, 'src/controllers/Accounts.php'), encoding='utf-8').read()
check('provider avatar is not stored', "UPDATE users SET avatar_url" not in acc)
oa = open(os.path.join(ROOT, 'src/OAuth.php'), encoding='utf-8').read()
check('access token is never written to the database or session', not re.search(r"(\$_SESSION\[[^\]]*\]\s*=\s*\$at|INSERT[^;]*access_token|refresh_token)", oa + acc))
check('no Google API other than OpenID Connect is called', len(set(re.findall(r'googleapis\.com/[^\']*', oauth))) == 2 and 'gmail' not in (oauth + oa).lower())
for src in ('src', 'views/templates', 'config'):
    out = subprocess.run(['grep', '-rIil', '-E', r'openai|stable.?diffusion|midjourney|deepfake|undress|nudify|image.?generation', os.path.join(ROOT, src)], capture_output=True, text=True).stdout.split()
    bad_files = [f for f in out if 'help.php' not in f]
    check(f'no image-generation / AI-imagery feature wired in {src}/', not bad_files, str(bad_files))
check('no file upload feature (nothing user-supplied is hosted)', subprocess.run(['grep', '-rIl', '-E', r'\$_FILES|type="file"', os.path.join(ROOT, 'src'), os.path.join(ROOT, 'views/templates')], capture_output=True, text=True).stdout.strip() == '')

# ---- the button + note only when configured (throw-away server with fake credentials; the real .env is untouched)
def free_port():
    s = socket.socket(); s.bind(('127.0.0.1', 0)); p = s.getsockname()[1]; s.close(); return p
port = free_port()
env = dict(os.environ, OAUTH_GOOGLE_CLIENT_ID='test-client.apps.googleusercontent.com', OAUTH_GOOGLE_CLIENT_SECRET='not-a-real-secret')
srv = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', '-t', 'htdocs', 'htdocs/index.php'], cwd=ROOT, env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
try:
    for _ in range(40):
        try: socket.create_connection(('127.0.0.1', port), timeout=0.3).close(); break
        except OSError: time.sleep(0.15)
    cfg = f'http://127.0.0.1:{port}'
    for lang in ('en', 'fr', 'ar'):
        s, h = fetch(cfg, '/account/login/', lang)
        check(f'[{lang}] configured: Google button on /account/login/', '/account/social/google/' in h)
        check(f'[{lang}] configured: data note shown next to the button', 'data-google-note' in h and '#pv-google' in h)
        s, h = fetch(cfg, '/account/register/', lang)
        check(f'[{lang}] configured: data note on /account/register/', 'data-google-note' in h)
    jar = http.cookiejar.CookieJar()
    class NoRedir(urllib.request.HTTPRedirectHandler):
        def redirect_request(self, *a, **k): return None
    op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar), NoRedir)
    try: op.open(cfg + '/account/social/google/', timeout=15)
    except urllib.error.HTTPError as e: loc = e.headers.get('Location', '')
    q = dict(re.findall(r'[?&]([a-z_]+)=([^&]*)', loc))
    check('authorize URL goes to accounts.google.com over https', loc.startswith('https://accounts.google.com/o/oauth2/v2/auth?'))
    check('authorize URL asks scope=openid email profile only', q.get('scope') == 'openid+email+profile', q.get('scope', ''))
    check('authorize URL carries a state and response_type=code', len(q.get('state', '')) >= 32 and q.get('response_type') == 'code')
    check('authorize URL is online access (no refresh token)', q.get('access_type') == 'online')
finally:
    srv.terminate()
    try: srv.wait(timeout=5)
    except Exception: srv.kill()

s, h = fetch(BASE, '/account/login/', 'en')
if 'accounts/social/google' not in h:
    check('not configured: no Google button and no Google note', 'data-google-note' not in h)

print(f'\ngoogle: {ok} passed, {bad} failed')
sys.exit(1 if bad else 0)
