#!/usr/bin/env python3
from __future__ import annotations
import re
from pathlib import Path
ROOT = Path(__file__).resolve().parents[3]
INDEX = ROOT / 'apps/platform/public/index.php'
APP = ROOT / 'apps/platform/public/assets/app.js'
CSS = ROOT / 'apps/platform/public/assets/app.css'
PRODUCT_DIR = ROOT / 'apps/platform/public/assets/ui-v2'
PRODUCT_CSS = ROOT / 'apps/platform/public/assets/ui-v2/product-ui.css'
SHELL_CSS = ROOT / 'apps/platform/public/assets/ui-v2/product-shell.css'
RESPONSIVE_CSS = ROOT / 'apps/platform/public/assets/ui-v2/product-shell-responsive.css'
ICONS = ROOT / 'apps/platform/public/assets/web-shell/icons.svg'

html = INDEX.read_text(encoding='utf-8')
app = APP.read_text(encoding='utf-8')
css = CSS.read_text(encoding='utf-8')
product_files = sorted(PRODUCT_DIR.glob('product-*.js'))
product = '\n'.join(path.read_text(encoding='utf-8') for path in product_files)
product_css = PRODUCT_CSS.read_text(encoding='utf-8')
shell_css = SHELL_CSS.read_text(encoding='utf-8') + RESPONSIVE_CSS.read_text(encoding='utf-8')
icons = ICONS.read_text(encoding='utf-8')

assert '<html lang="fa" dir="rtl">' in html
assert 'id="dashboard" aria-labelledby="greeting" tabindex="-1"' in html
assert 'class="skip-link" href="#main-content"' in html
for route in ['home','courses','schedule','resources','assessments','grades','announcements','forms','orders','account','management','search']:
    assert route in product, route
for label in ['خانه','درس‌ها','برنامه','یادگیری و منابع','آزمون‌ها','نمرات','اطلاعیه‌ها','فرم‌ها','خرید و دسترسی','مدیریت']:
    assert label in html or label in product, label
assert 'mobile-bottom-nav' in html and 'desktop-sidebar' in html
assert html.count('/assets/ui-v2/product-shell.css') == 1
assert html.count('/assets/ui-v2/product-shell-responsive.css') == 1
for asset in ['product-core.js','product-learning.js','product-academic.js','product-communication.js','product-account.js','product-ui.js']:
    assert html.count('/assets/ui-v2/' + asset) == 1, asset
assert 'role="dialog" aria-modal="true"' in html
assert 'aria-live="polite"' in html
assert 'aria-current' in app
assert "setAttribute('aria-pressed',active?'true':'false')" in app
assert "if(focus)$('#dashboard').focus()" in app
assert 'workspaceMutation=true;select.disabled=true;++state.requestSerial' in app
assert 'serial !== state.requestSerial' in app
assert 'Promise.all([' in app
assert 'safeRead(' in app
assert 'fanoos_workspace' not in app
assert '.innerHTML=' not in app and not re.search(r'\.innerHTML\s*=', product)
assert not re.search(r'\balert\s*\(', app)
assert 'product_id' not in html.lower(), 'developer product ID must not be a user input'
assert 'Update Server' not in html and 'به‌روزرسانی سرور' not in html
for leak in ['storage_key','provider_reference','private_file_path']:
    assert leak not in html.lower()
for size in ['max-width: 840px','max-width: 520px']:
    assert size in css or size in shell_css
for selector in ['.mobile-bottom-nav','.desktop-sidebar','.product-shell','.toast-region']:
    assert selector in css or selector in shell_css
for selector in ['.course-grid','.timeline','.resource-grid','.grade-table','.filter-bar','.tabs','.state-block']:
    assert selector in product_css
assert '@media (prefers-reduced-motion: reduce)' in css or '@media (prefers-reduced-motion: reduce)' in shell_css
assert ':focus-visible' in css or ':focus-visible' in shell_css
assert 'unicode-bidi: isolate' in product_css or 'unicode-bidi:isolate' in product_css
assert icons.count('<symbol ') >= 15
assert not re.search(r'https?://(?:fonts|cdn|unpkg|jsdelivr)', html + css + shell_css + product_css, re.I)
print('ui-v2 web static contract: PASS')
