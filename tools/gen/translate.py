#!/usr/bin/env python3
"""Fill missing fr/ar in data/content/**/*.json (dicts that have a string 'en' key).
Cache: tools/gen/_tr_cache.json (key = lang + sha1(en)). Re-runs are free.
Usage: python3 tools/gen/translate.py [glob-subdir ...]"""
import json, os, sys, glob, hashlib, re, threading, time
from concurrent.futures import ThreadPoolExecutor
import requests

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
CACHE = os.path.join(ROOT, 'tools/gen/_tr_cache.json')
BASE = os.environ.get('OPENAI_BASE_URL', 'https://www.genspark.ai/api/llm_proxy/v1').rstrip('/')
KEY = os.environ.get('OPENAI_API_KEY', '')
LANGS = {'fr': 'French', 'ar': 'Modern Standard Arabic'}
lock = threading.Lock()
cache = json.load(open(CACHE)) if os.path.exists(CACHE) else {}

def h(lang, s): return lang + ':' + hashlib.sha1(s.encode()).hexdigest()

SYS = ("You are a professional translator for a company website (investor and expert-recruitment copy, "
       "sober, precise, no hype). Translate each string of the JSON array from English into {L}. "
       "Rules: keep placeholders like {{ a.b }} / {{ a.b|num }} EXACTLY unchanged; keep markdown markers (**bold**, *em*, `code`, "
       "[text](url) with url unchanged); keep numbers, currency codes (TND), URLs, emails, slugs, and brand/product names "
       "(W5D, Dyon, MetaX Academy, KernID, SelfAI, WebBooks, 2030B, Studio, SONEDE, CCDR, SDG, KPI, PDF/A, CSV, JSON, API) unchanged. "
       "Keep ids like F1/E1/L2 unchanged. {EXTRA}Return ONLY a JSON array of strings with exactly the same length and order.")
EXTRA = {'fr': 'Use formal vous. ', 'ar': 'Write natural, formal Arabic; keep Latin brand names and digits as Latin. '}

def call(lang, items, retries=4):
    body = {'model': 'gpt-5-mini', 'messages': [
        {'role': 'system', 'content': SYS.replace('{L}', LANGS[lang]).replace('{EXTRA}', EXTRA[lang])},
        {'role': 'user', 'content': json.dumps(items, ensure_ascii=False)}]}
    for a in range(retries):
        try:
            r = requests.post(BASE + '/chat/completions', headers={'Authorization': 'Bearer ' + KEY}, json=body, timeout=180)
            txt = r.json()['choices'][0]['message']['content'].strip()
            txt = re.sub(r'^```(?:json)?|```$', '', txt, flags=re.M).strip()
            out = json.loads(txt)
            if isinstance(out, list) and len(out) == len(items) and all(isinstance(x, str) for x in out):
                return out
        except Exception as e:
            time.sleep(2 + a * 2)
    return None

def translate(lang, texts):
    todo = [t for t in dict.fromkeys(texts) if h(lang, t) not in cache]
    batches, cur, n = [], [], 0
    for t in todo:
        if cur and (len(cur) >= 20 or n + len(t) > 5000): batches.append(cur); cur, n = [], 0
        cur.append(t); n += len(t)
    if cur: batches.append(cur)
    def work(b):
        out = call(lang, b)
        if out is None and len(b) > 1:
            out = []
            for t in b:
                o = call(lang, [t]); out.append(o[0] if o else None)
        for t, o in zip(b, out or [None] * len(b)):
            if o:
                with lock: cache[h(lang, t)] = o
        with lock: json.dump(cache, open(CACHE, 'w'), ensure_ascii=False)
    with ThreadPoolExecutor(8) as ex: list(ex.map(work, batches))

def walk(o, acc):
    if isinstance(o, dict):
        if isinstance(o.get('en'), str) and set(o) <= {'en', 'fr', 'ar'}:
            acc.append(o); return
        for v in o.values(): walk(v, acc)
    elif isinstance(o, list):
        for v in o: walk(v, acc)

def main():
    subs = sys.argv[1:] or ['*']
    files = []
    for s in subs: files += glob.glob(os.path.join(ROOT, 'data/content', s, '*.json')) if s != '**' else glob.glob(os.path.join(ROOT, 'data/content/**/*.json'), recursive=True)
    docs = {}
    need = {'fr': [], 'ar': []}
    for f in sorted(files):
        j = json.load(open(f)); acc = []; walk(j, acc); docs[f] = (j, acc)
        for d in acc:
            for l in need:
                if l not in d and d['en']: need[l].append(d['en'])
    for l in need:
        print(l, 'strings to translate:', len(set(need[l])), flush=True)
        translate(l, need[l])
    miss = 0
    for f, (j, acc) in docs.items():
        ch = False
        for d in acc:
            for l in LANGS:
                if l not in d:
                    v = cache.get(h(l, d['en']))
                    if v: d[l] = v; ch = True
                    else: miss += 1
        if ch: json.dump(j, open(f, 'w'), ensure_ascii=False, indent=1); open(f, 'a').write('\n')
    print('missing after run:', miss)
main()
