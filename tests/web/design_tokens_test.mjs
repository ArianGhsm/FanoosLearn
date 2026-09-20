/*
 * The design tokens define every colour, shadow and radius the site uses, in
 * three blocks: the light `:root`, the system-dark media query, and the
 * explicit `[data-theme="dark"]` override. The two dark blocks must stay
 * identical, and both must cover the light one.
 *
 * This is tested because the failure is invisible rather than loud. A token
 * added to only one dark block does not render as a missing colour -- it
 * silently inherits the *light* value, so the page looks right to anyone who
 * has touched the theme toggle and subtly wrong only for a viewer sitting on
 * system-dark. That is exactly the bug this file was written after: six new
 * tokens landed in `[data-theme="dark"]` and not in the media query, because
 * the media query's declarations are indented one level deeper and a
 * search-and-replace matched only the shallower copy.
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const TOKENS = new URL('../../apps/platform/public/assets/web/foundation/tokens.css', import.meta.url);
const css = readFileSync(TOKENS, 'utf8');

/** Every `--name: value` declaration inside one brace-balanced block. */
function declarationsIn(source) {
    const found = new Map();
    for (const match of source.matchAll(/--([a-z0-9-]+)\s*:\s*([^;]+);/gi)) {
        found.set(match[1], match[2].trim().replace(/\s+/g, ' '));
    }
    return found;
}

/** The body of the block starting at `openIndex` (the index of its `{`). */
function blockAt(source, openIndex) {
    let depth = 0;
    for (let i = openIndex; i < source.length; i += 1) {
        if (source[i] === '{') depth += 1;
        if (source[i] === '}') {
            depth -= 1;
            if (depth === 0) return source.slice(openIndex + 1, i);
        }
    }
    throw new Error('Unbalanced braces in tokens.css.');
}

function blockAfter(marker) {
    const start = css.indexOf(marker);
    assert.notEqual(start, -1, `tokens.css no longer contains ${marker}`);
    return declarationsIn(blockAt(css, css.indexOf('{', start)));
}

const light = blockAfter(':root {');
const systemDark = blockAfter('@media (prefers-color-scheme: dark)');
const explicitDark = blockAfter(':root[data-theme="dark"]');

test('the tokens file still has all three blocks with tokens in them', () => {
    assert.ok(light.size > 20, 'light :root block looks empty');
    assert.ok(systemDark.size > 20, 'system-dark block looks empty');
    assert.ok(explicitDark.size > 20, 'explicit-dark block looks empty');
});

test('both dark blocks define exactly the same token names', () => {
    const inSystemOnly = [...systemDark.keys()].filter((name) => !explicitDark.has(name));
    const inExplicitOnly = [...explicitDark.keys()].filter((name) => !systemDark.has(name));
    assert.deepEqual(inSystemOnly, [], 'tokens defined for system dark but not for the explicit dark theme');
    assert.deepEqual(
        inExplicitOnly,
        [],
        'tokens defined for the explicit dark theme but not for system dark -- a viewer on system dark who never '
        + 'touched the toggle silently gets the LIGHT value for these',
    );
});

test('both dark blocks give every shared token the same value', () => {
    const differing = [...explicitDark.entries()]
        .filter(([name, value]) => systemDark.has(name) && systemDark.get(name) !== value)
        .map(([name]) => name);
    assert.deepEqual(differing, [], 'the two dark blocks disagree on these token values');
});

test('every dark token overrides one the light theme actually defines', () => {
    // `color-scheme` is a real property, not a token, so it is absent here by
    // construction -- this checks that a dark block never invents a token name
    // no light value exists for, which would leave it undefined in light mode.
    const orphans = [...explicitDark.keys()].filter((name) => !light.has(name));
    assert.deepEqual(orphans, [], 'dark-only tokens with no light-theme definition');
});

test('the tokens the redesign introduced are present in all three blocks', () => {
    for (const name of ['shadow-lift', 'shadow-accent', 'accent-gradient', 'aura-1', 'aura-2', 'aura-3']) {
        assert.ok(light.has(name), `--${name} missing from the light theme`);
        assert.ok(systemDark.has(name), `--${name} missing from system dark`);
        assert.ok(explicitDark.has(name), `--${name} missing from the explicit dark theme`);
    }
});

test('motion tokens are theme-independent and defined once, in the light root', () => {
    for (const name of ['ease', 'motion-fast', 'motion-base']) {
        assert.ok(light.has(name), `--${name} missing from :root`);
        assert.ok(!systemDark.has(name), `--${name} should not be redefined per theme`);
        assert.ok(!explicitDark.has(name), `--${name} should not be redefined per theme`);
    }
});
