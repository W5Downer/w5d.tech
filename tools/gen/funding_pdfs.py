#!/usr/bin/env python3
"""Build docs/funding/*.pdf from the .txt sources (the .txt files are what Funding::channelAvailable() checks).
Page 1 carries the key clauses; remaining clauses follow on page 2. Needs reportlab (build-time only; the app has no such dependency).
Run:  python3 tools/gen/funding_pdfs.py
"""
import re, sys, pathlib
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.units import mm
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, PageBreak, Table, TableStyle
from reportlab.lib import colors

ROOT = pathlib.Path(__file__).resolve().parents[2]
OUT = ROOT / 'docs' / 'funding'
H = ParagraphStyle('h', fontName='Helvetica-Bold', fontSize=14, leading=18, spaceAfter=4)
S = ParagraphStyle('s', fontName='Helvetica-Oblique', fontSize=8.5, leading=11, textColor=colors.HexColor('#555555'), spaceAfter=8)
C = ParagraphStyle('c', fontName='Helvetica', fontSize=10, leading=14, spaceAfter=7)
F = ParagraphStyle('f', parent=C, fontSize=9, textColor=colors.HexColor('#333333'))
B = ParagraphStyle('b', parent=C, backColor=colors.HexColor('#f3f0ff'), borderPadding=5, spaceAfter=10)

def parse(path):
    raw = path.read_text(encoding='utf-8').strip().split('\n\n')
    head = raw[0].split('\n')
    clauses = []
    for blk in raw[1:]:
        m = re.match(r'\[([a-z\-]+)\]\s*(.*)', blk.strip(), re.S)
        if m: clauses.append((m.group(1), re.sub(r'\s+', ' ', m.group(2))))
    return head, clauses

def esc(s): return s.replace('&', '&amp;').replace('<', '&lt;').replace('>', '&gt;')

def build(src, dst, first, ph):
    head, cl = parse(OUT / src)
    d = dict(cl)
    story = [Paragraph(esc(head[0]), H), Paragraph(esc(head[1]), S)]
    t = Table([[Paragraph(esc(head[2]), F)]], colWidths=[170*mm]); t.setStyle(TableStyle([('BOX', (0,0), (-1,-1), .6, colors.HexColor('#999999')), ('BACKGROUND', (0,0), (-1,-1), colors.HexColor('#fafafa'))]))
    story += [t, Spacer(1, 8)]
    for k in first:
        story.append(Paragraph(esc(d[k]), B))
        fr = d.get('fr-' + k)
        if fr: story.append(Paragraph(esc(fr), F))
    story.append(PageBreak())
    for k, v in cl:
        if k in first or k.startswith('fr-'): continue
        story.append(Paragraph(esc(v), C))
        if ('fr-' + k) in d: story.append(Paragraph(esc(d['fr-' + k]), F))
    story += [Spacer(1, 14), Paragraph('Signature of the parties (after counsel approval): ____________________   ____________________', F)]
    SimpleDocTemplate(str(OUT / dst), pagesize=A4, leftMargin=20*mm, rightMargin=20*mm, topMargin=18*mm, bottomMargin=18*mm, title=head[0], author='W5D TECH').build(story)

env = {}
for line in (ROOT / '.env.example').read_text().splitlines():
    if '=' in line and not line.startswith('#'):
        k, v = line.split('=', 1); env[k.strip()] = re.sub(r'\s+#.*$', '', v).strip().strip('"')
cond = env['F4_CONDITION_PRECEDENT']
if cond not in (OUT / 'f4-presale-contract.txt').read_text():
    sys.exit('F4 template does not contain the condition precedent verbatim: ' + cond)
build('f2-sponsorship-agreement.txt', 'f2-sponsorship-agreement.pdf', ['restriction', 'refund', 'no-return'], {})
build('f4-presale-contract.txt', 'f4-presale-contract.pdf', ['condition-precedent', 'escrow'], {})
print('written: f2-sponsorship-agreement.pdf, f4-presale-contract.pdf')
