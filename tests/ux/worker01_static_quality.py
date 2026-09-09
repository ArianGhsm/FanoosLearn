#!/usr/bin/env python3
"""Static UX guard for the retained V2 visual-shell reference."""
from __future__ import annotations

import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
INDEX = ROOT / "tests/fixtures/ui-v2/index.php"
CSS = ROOT / "apps/platform/public/assets/app.css"
ICONS = ROOT / "apps/platform/public/assets/web-shell/icons.svg"

html = INDEX.read_text(encoding="utf-8")
css = CSS.read_text(encoding="utf-8")
icons = ICONS.read_text(encoding="utf-8")

failures: list[str] = []

def require(condition: bool, message: str) -> None:
    if not condition:
        failures.append(message)

require(bool(re.search(r'<html[^>]*\blang="fa"[^>]*\bdir="rtl"', html)), "html must remain lang=fa and dir=rtl")
required_ids = {"login-panel", "login-form", "dashboard", "workspace-picker", "workspace-select", "logout", "greeting", "workspace-path", "today", "result-list", "view-title", "search-form"}
for item_id in sorted(required_ids):
    require(f'id="{item_id}"' in html, f"required app.js hook #{item_id} is missing")
required_views = {"schedule", "grades", "announcements", "academics", "resources", "assessments", "forms", "orders"}
actual_views = set(re.findall(r'data-view="([a-z-]+)"', html))
require(required_views == actual_views, f"data-view hooks changed: expected {sorted(required_views)}, got {sorted(actual_views)}")
for abbreviation in ("تق", "نم", "اع", "در", "من", "آز", "فر", "خر"):
    require(not re.search(rf">\s*{re.escape(abbreviation)}\s*<", html), f"legacy visible module abbreviation remains: {abbreviation}")
require('/assets/web-shell/icons.svg#' in html, "module icon system must use the local web-shell SVG sprite")
require(icons.count("<symbol ") >= 8, "local icon sprite must provide all module symbols")
require('aria-hidden="true"' in html and 'module-icon' in html, "decorative module icons must be hidden from assistive technology")
remote_html_dependency = re.search(r'<(?:link|script)\b[^>]*(?:href|src)="https?://', html, flags=re.I)
remote_css_dependency = re.search(r'(?:@import\s+|url\(\s*["\']?)https?://', css, flags=re.I)
require(remote_html_dependency is None, "remote font/script/icon dependency found in shell HTML")
require(remote_css_dependency is None, "remote font/icon dependency found in CSS")
require('class="skip-link" href="#main-content"' in html, "skip-to-content link is missing")
require('<main class="site-main" id="main-content"' in html, "main landmark target is missing")
for landmark in ("<header", "<main", "<nav", "<footer"):
    require(landmark in html, f"semantic landmark missing: {landmark}")
require(':focus-visible' in css, "visible keyboard focus rule is missing")
require('prefers-reduced-motion: reduce' in css, "reduced-motion rule is missing")
require('aria-live="polite"' in html, "shell should expose polite live regions for asynchronous status/results")
require('for="login-identifier"' in html and 'for="login-password"' in html, "login inputs need explicit labels")
for breakpoint in ("max-width: 840px", "max-width: 520px"):
    require(breakpoint in css, f"responsive breakpoint missing: {breakpoint}")
require('--touch-target: 44px' in css, "safe touch-target token is missing")
require('min-width: 320px' in css, "320px minimum viewport guard is missing")
require('overflow-x: hidden' in css, "horizontal overflow guard is missing")
required_tokens = {"--color-background", "--color-surface", "--color-surface-raised", "--color-text", "--color-text-secondary", "--color-text-muted", "--color-border", "--color-primary", "--color-primary-hover", "--color-success", "--color-warning", "--color-error", "--color-info", "--shadow-sm", "--radius-md", "--space-4", "--font-sans", "--focus-ring", "--motion-normal"}
for token in sorted(required_tokens):
    require(token in css, f"design token missing: {token}")
for primitive in (".ui-state-loading", ".ui-state-empty", ".ui-state-error", ".ui-state-warning", ".ui-state-success", ".ui-state-info", ".skeleton", ".status-chip", ".metadata-row", ".button-primary", ".button-secondary", ".button-quiet", ".button-danger", ".surface-card", ".list-stack", ".card-grid"):
    require(primitive in css, f"visual primitive missing: {primitive}")
sensitive_patterns = {"environment variable": r"\bFANOOS_[A-Z0-9_]+\b", "authorization header": r"\bAuthorization\b", "secret assignment": r"\b(?:secret|api[_-]?key|access[_-]?token|bot[_-]?token)\s*[=:]", "chat id": r"\bchat[_-]?id\s*[=:]"}
for label, pattern in sensitive_patterns.items():
    require(re.search(pattern, html, flags=re.I) is None, f"possible inline runtime/secret data found ({label})")
if failures:
    print("worker01 static quality: FAIL")
    for failure in failures:
        print(f" - {failure}")
    raise SystemExit(1)
print("worker01 static quality: PASS")
