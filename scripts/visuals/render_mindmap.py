#!/usr/bin/env python3
"""Draw a FANOOS mind map as a picture (SVG, then PNG through rsvg-convert).

The topic sits in the middle in the brand gradient; its branches spread to
both sides, each in its own hue with a curved line of the same hue; deeper
items are pale boxes of their branch's hue. A faint tiled «فانوس» watermark
and a small stamp (logo, site, the book pages it was made from) finish it.

Input: a visuals source file (fanoos.bank.visuals/1 with `tree` bodies).
Output: one PNG per visual in --out, and --import, the same file with each
`tree` replaced by `image` so `import-bank.php import --assets=<out>` stores it.

    python3 scripts/visuals/render_mindmap.py SOURCE.json --out DIR --import OUT.json [--logo PNG]

Needs: fonts-vazirmatn, python3-pil with raqm, librsvg2-bin.
"""
from __future__ import annotations

import argparse
import base64
import copy
import json
import pathlib
import subprocess
import sys
from xml.sax.saxutils import escape

from PIL import ImageFont

FONT_DIR = pathlib.Path('/usr/share/fonts/truetype/vazirmatn')
FONTS = {w: str(FONT_DIR / f'Vazirmatn-{w}.ttf') for w in ('Regular', 'Medium', 'SemiBold', 'Bold')}
FA = str.maketrans('0123456789', '۰۱۲۳۴۵۶۷۸۹')


def fa(text: str) -> str:
    """Persian digits, except inside Latin words (14e, USP6, t(16;17))."""
    return ' '.join(w if any('a' <= ch.lower() <= 'z' for ch in w) else w.translate(FA) for w in text.split(' '))

# The site's category hues (tokens.css), one per branch, in reading order.
HUES = ['#307fe2', '#0f9488', '#6f42c1', '#d63384', '#ff7a00', '#12a150']
INK = '#101a29'
MUTED = '#5b6474'

STYLE = {
    0: dict(size=21, weight='Bold', pad_x=22, pad_y=12, max_w=260, line=1.55, radius=16),
    1: dict(size=15, weight='SemiBold', pad_x=14, pad_y=8, max_w=210, line=1.6, radius=11),
    2: dict(size=13, weight='Medium', pad_x=11, pad_y=6, max_w=250, line=1.65, radius=9),
    3: dict(size=12, weight='Regular', pad_x=10, pad_y=5, max_w=240, line=1.65, radius=8),
}
NOTE = dict(size=10.5, weight='Regular', line=1.55)
GAP_X = 44
GAP_Y = 9
MARGIN = 36

_fonts: dict[tuple[str, float], ImageFont.FreeTypeFont] = {}


def font(weight: str, size: float) -> ImageFont.FreeTypeFont:
    key = (weight, size)
    if key not in _fonts:
        _fonts[key] = ImageFont.truetype(FONTS[weight], round(size * 4), layout_engine=ImageFont.Layout.RAQM)
    return _fonts[key]


def width(text: str, weight: str, size: float) -> float:
    return font(weight, size).getlength(text, direction='rtl') / 4


def wrap(text: str, weight: str, size: float, max_w: float) -> list[str]:
    lines, line = [], ''
    for word in text.split():
        trial = f'{line} {word}'.strip()
        if line and width(trial, weight, size) > max_w:
            lines.append(line)
            line = word
        else:
            line = trial
    if line:
        lines.append(line)
    return lines or ['']


class Node:
    def __init__(self, data: dict, depth: int, hue: str | None):
        self.text = str(data.get('text', '')).strip()
        self.note = str(data.get('note', '') or '').strip()
        self.depth = depth
        self.hue = hue
        self.children: list[Node] = []
        st = STYLE[min(depth, 3)]
        self.st = st
        self.lines = wrap(self.text, st['weight'], st['size'], st['max_w'])
        self.note_lines = wrap(self.note, NOTE['weight'], NOTE['size'], st['max_w']) if self.note else []
        text_w = max(width(l, st['weight'], st['size']) for l in self.lines)
        note_w = max((width(l, NOTE['weight'], NOTE['size']) for l in self.note_lines), default=0)
        self.w = max(text_w, note_w) + 2 * st['pad_x']
        self.h = (len(self.lines) * st['size'] * st['line'] + len(self.note_lines) * NOTE['size'] * NOTE['line']
                  + 2 * st['pad_y'])
        self.x = self.y = 0.0  # top-left
        self.side = 0  # +1 right of the root, -1 left

    def span(self) -> float:
        if not self.children:
            return self.h
        inner = sum(c.span() for c in self.children) + GAP_Y * (len(self.children) - 1)
        return max(self.h, inner)


def build(data: dict, depth: int = 0, hue: str | None = None, index: int = 0) -> Node:
    if depth == 1:
        hue = HUES[index % len(HUES)]
    node = Node(data, depth, hue)
    for i, child in enumerate(data.get('children') or []):
        node.children.append(build(child, depth + 1, hue, i))
    return node


def place(node: Node, top: float, side: int) -> None:
    """Children stacked inside node's band, each starting past node's edge."""
    node.side = side
    band = node.span()
    node.y = top + (band - node.h) / 2
    if not node.children:
        return
    inner = sum(c.span() for c in node.children) + GAP_Y * (len(node.children) - 1)
    y = top + (band - inner) / 2
    for child in node.children:
        child.x = node.x + node.w + GAP_X if side > 0 else node.x - GAP_X - child.w
        place(child, y, side)
        y += child.span() + GAP_Y


def walk(node: Node):
    yield node
    for child in node.children:
        yield from walk(child)


def layout(root: Node) -> None:
    # Balance the branches between the two sides by height; the reader
    # starts on the right, so the first branches go there.
    right, left, hr, hl = [], [], 0.0, 0.0
    for branch in root.children:
        if hr <= hl:
            right.append(branch)
            hr += branch.span() + GAP_Y
        else:
            left.append(branch)
            hl += branch.span() + GAP_Y
    root.x, root.y = 0.0, 0.0
    for group, side in ((right, 1), (left, -1)):
        total = sum(b.span() for b in group) + GAP_Y * max(0, len(group) - 1)
        y = root.h / 2 - total / 2
        for branch in group:
            branch.x = root.x + root.w + GAP_X * 1.3 if side > 0 else root.x - GAP_X * 1.3 - branch.w
            place(branch, y, side)
            y += branch.span() + GAP_Y
    root.side = 0


def tint(hex_color: str, amount: float) -> str:
    """Mix a hue with white; amount 0 = white, 1 = the hue."""
    h = hex_color.lstrip('#')
    r, g, b = (int(h[i:i + 2], 16) for i in (0, 2, 4))
    mix = lambda c: round(255 + (c - 255) * amount)
    return f'#{mix(r):02x}{mix(g):02x}{mix(b):02x}'


def text_block(node: Node, cx: float, color: str, note_color: str) -> list[str]:
    st = node.st
    out = []
    y = node.y + st['pad_y']
    for line in node.lines:
        y += st['size'] * st['line']
        out.append(f'<text x="{cx:.1f}" y="{y - st["size"] * (st["line"] - 1) / 2 - 2:.1f}" font-size="{st["size"]}" '
                   f'font-weight="{ {"Regular": 400, "Medium": 500, "SemiBold": 600, "Bold": 700}[st["weight"]] }" '
                   f'fill="{color}">{escape(fa(line))}</text>')
    for line in node.note_lines:
        y += NOTE['size'] * NOTE['line']
        out.append(f'<text x="{cx:.1f}" y="{y - NOTE["size"] * (NOTE["line"] - 1) / 2 - 1.5:.1f}" font-size="{NOTE["size"]}" '
                   f'font-weight="400" fill="{note_color}">{escape(fa(line))}</text>')
    return out


def svg(visual: dict, logo_b64: str | None, source_line: str) -> tuple[str, float, float]:
    root = build(visual['tree'])
    layout(root)
    nodes = list(walk(root))
    min_x = min(n.x for n in nodes)
    max_x = max(n.x + n.w for n in nodes)
    min_y = min(n.y for n in nodes)
    max_y = max(n.y + n.h for n in nodes)
    head = 46
    foot = 58
    W = max_x - min_x + 2 * MARGIN
    H = max_y - min_y + 2 * MARGIN + head + foot
    dx = MARGIN - min_x
    dy = MARGIN + head - min_y
    for n in nodes:
        n.x += dx
        n.y += dy

    parts = [
        f'<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="{W:.0f}" height="{H:.0f}" '
        f'viewBox="0 0 {W:.0f} {H:.0f}" font-family="Vazirmatn" direction="rtl" text-anchor="middle">',
        '<defs>'
        '<linearGradient id="brand" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#ff5a6c"/>'
        '<stop offset="0.55" stop-color="#ef4056"/><stop offset="1" stop-color="#e0344b"/></linearGradient>'
        '<filter id="lift" x="-20%" y="-20%" width="140%" height="160%"><feDropShadow dx="0" dy="3" stdDeviation="4" '
        'flood-color="#101a29" flood-opacity="0.12"/></filter>'
        '<filter id="glow" x="-30%" y="-40%" width="160%" height="200%"><feDropShadow dx="0" dy="6" stdDeviation="8" '
        'flood-color="#ef4056" flood-opacity="0.30"/></filter>'
        '<pattern id="dots" width="18" height="18" patternUnits="userSpaceOnUse"><circle cx="2" cy="2" r="1" fill="#e6e8ee"/></pattern>'
        '</defs>',
        f'<rect width="{W:.0f}" height="{H:.0f}" rx="22" fill="#ffffff"/>',
        f'<rect x="10" y="10" width="{W - 20:.0f}" height="{H - 20:.0f}" rx="16" fill="url(#dots)"/>',
    ]
    # Watermark: faint, tiled, diagonal.
    wm = []
    step_x, step_y = 230, 130
    for row in range(int(H // step_y) + 2):
        for col in range(int(W // step_x) + 2):
            x = col * step_x + (row % 2) * step_x / 2
            y = row * step_y + 40
            wm.append(f'<text x="{x:.0f}" y="{y:.0f}" transform="rotate(-24 {x:.0f} {y:.0f})" font-size="20" '
                      f'font-weight="700" fill="#101a29" fill-opacity="0.022">فانوس · fanooslearn.ir</text>')
    parts.append('<g>' + ''.join(wm) + '</g>')

    # Title strip.
    parts.append(f'<text x="{W / 2:.1f}" y="{MARGIN + 6:.1f}" font-size="13" font-weight="600" fill="{MUTED}">'
                 f'جمع‌بندی مبحث · {escape(fa(visual["title"]))}</text>')

    # Connectors under the boxes.
    lines = []
    for n in nodes:
        for c in n.children:
            if n.depth == 0:
                sx = n.x + n.w if c.side > 0 else n.x
            else:
                sx = n.x + n.w if n.side > 0 else n.x
            sy = n.y + n.h / 2
            ex = c.x if c.side > 0 else c.x + c.w
            ey = c.y + c.h / 2
            mx = (sx + ex) / 2
            stroke = c.hue or '#9aa3b2'
            sw = 2.6 if c.depth == 1 else (1.9 if c.depth == 2 else 1.4)
            op = 0.95 if c.depth == 1 else 0.75
            lines.append(f'<path d="M{sx:.1f},{sy:.1f} C{mx:.1f},{sy:.1f} {mx:.1f},{ey:.1f} {ex:.1f},{ey:.1f}" fill="none" '
                         f'stroke="{stroke}" stroke-width="{sw}" stroke-opacity="{op}" stroke-linecap="round"/>')
    parts.append('<g>' + ''.join(lines) + '</g>')

    # Boxes and text.
    for n in nodes:
        st = n.st
        cx = n.x + n.w / 2
        if n.depth == 0:
            parts.append(f'<rect x="{n.x:.1f}" y="{n.y:.1f}" width="{n.w:.1f}" height="{n.h:.1f}" rx="{st["radius"]}" '
                         f'fill="url(#brand)" filter="url(#glow)"/>')
            parts += text_block(n, cx, '#ffffff', '#ffe3e7')
        elif n.depth == 1:
            parts.append(f'<rect x="{n.x:.1f}" y="{n.y:.1f}" width="{n.w:.1f}" height="{n.h:.1f}" rx="{st["radius"]}" '
                         f'fill="{n.hue}" filter="url(#lift)"/>')
            parts += text_block(n, cx, '#ffffff', tint('#ffffff', 0.85))
        elif n.depth == 2:
            parts.append(f'<rect x="{n.x:.1f}" y="{n.y:.1f}" width="{n.w:.1f}" height="{n.h:.1f}" rx="{st["radius"]}" '
                         f'fill="{tint(n.hue, 0.12)}" stroke="{tint(n.hue, 0.45)}" stroke-width="1.2"/>')
            parts += text_block(n, cx, INK, MUTED)
        else:
            parts.append(f'<rect x="{n.x:.1f}" y="{n.y:.1f}" width="{n.w:.1f}" height="{n.h:.1f}" rx="{st["radius"]}" '
                         f'fill="#ffffff" stroke="{tint(n.hue, 0.55)}" stroke-width="1.1" stroke-dasharray="0"/>')
            parts += text_block(n, cx, INK, MUTED)

    # Footer: the book on one side, the stamp on the other.
    fy = H - MARGIN + 4
    parts.append(f'<line x1="{MARGIN}" y1="{fy - 30:.1f}" x2="{W - MARGIN:.1f}" y2="{fy - 30:.1f}" stroke="#e4e6eb" stroke-width="1"/>')
    parts.append(f'<text x="{W - MARGIN:.1f}" y="{fy:.1f}" text-anchor="start" font-size="11.5" fill="{MUTED}">'
                 f'{escape(source_line)}</text>')
    stamp_x = MARGIN
    if logo_b64:
        parts.append(f'<image x="{stamp_x}" y="{fy - 20:.1f}" width="26" height="26" '
                     f'xlink:href="data:image/png;base64,{logo_b64}" href="data:image/png;base64,{logo_b64}"/>')
        stamp_x += 32
    parts.append(f'<text x="{stamp_x:.1f}" y="{fy - 4:.1f}" direction="ltr" text-anchor="start" font-size="13" font-weight="700" '
                 f'fill="#ef4056">فانوس</text>')
    parts.append(f'<text x="{stamp_x + 44:.1f}" y="{fy - 4:.1f}" direction="ltr" text-anchor="start" font-size="11" fill="{MUTED}">'
                 f'fanooslearn.ir</text>')
    parts.append('</svg>')
    return '\n'.join(parts), W, H


def source_line(visual: dict, editions: dict[str, str]) -> str:
    frm = visual.get('from') or {}
    ref = frm.get('ref', '')
    name = editions.get(ref, ref)
    pages = ''
    if frm.get('pdf_page_from'):
        a, b = frm['pdf_page_from'], frm.get('pdf_page_to') or frm['pdf_page_from']
        pages = f' · صفحه‌ی {a}' if a == b else f' · صفحه‌های {a} تا {b}'
    return fa(f'منبع: {name}{pages}')


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument('source')
    ap.add_argument('--out', required=True)
    ap.add_argument('--import', dest='import_file', required=True)
    ap.add_argument('--logo')
    ap.add_argument('--books', help='JSON {"reference@edition": "Book name edition"}')
    ap.add_argument('--only')
    args = ap.parse_args()
    data = json.loads(pathlib.Path(args.source).read_text(encoding='utf-8'))
    out = pathlib.Path(args.out)
    out.mkdir(parents=True, exist_ok=True)
    logo = base64.b64encode(pathlib.Path(args.logo).read_bytes()).decode() if args.logo else None
    books = json.loads(pathlib.Path(args.books).read_text(encoding='utf-8')) if args.books else {}
    result = copy.deepcopy(data)
    result['visuals'] = []
    for visual in data['visuals']:
        if 'tree' not in visual or (args.only and visual['key'] != args.only):
            continue
        doc, w, h = svg(visual, logo, source_line(visual, books))
        svg_path = out / f'{visual["key"]}.svg'
        png_path = out / f'{visual["key"]}.png'
        svg_path.write_text(doc, encoding='utf-8')
        subprocess.run(['rsvg-convert', '-z', '2', '-o', str(png_path), str(svg_path)], check=True)
        svg_path.unlink()
        item = {k: v for k, v in visual.items() if k != 'tree'}
        item['image'] = png_path.name
        result['visuals'].append(item)
        print(f'{png_path.name}  {w:.0f}x{h:.0f}', file=sys.stderr)
    pathlib.Path(args.import_file).write_text(json.dumps(result, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    return 0


if __name__ == '__main__':
    sys.exit(main())
