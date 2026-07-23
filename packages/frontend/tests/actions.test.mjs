import { test } from 'node:test';
import assert from 'node:assert/strict';

import {
    registerAction,
    onAction,
    getAction,
    hasAction,
    runAction,
    toggleAction,
    copyAction,
    initActions,
} from '../src/core/actions.js';
import { installDocument, el } from './dom-stub.mjs';

// ── registry ──

test('registerAction / getAction / hasAction round-trip', () => {
    const fn = () => {};
    registerAction('reg-x', fn);
    assert.equal(getAction('reg-x'), fn);
    assert.equal(hasAction('reg-x'), true);
    assert.equal(hasAction('reg-nope'), false);
});

test('onAction is an alias for registerAction', () => {
    assert.equal(onAction, registerAction);
});

test('registerAction replaces an existing handler of the same name', () => {
    const first = () => {};
    const second = () => {};
    registerAction('reg-dup', first);
    registerAction('reg-dup', second);
    assert.equal(getAction('reg-dup'), second);
});

test('registerAction rejects bad arguments', () => {
    assert.throws(() => registerAction('', () => {}), TypeError);
    assert.throws(() => registerAction('x', 'not-a-fn'), TypeError);
});

test('runAction runs a registered action and reports found/not-found', () => {
    let seen = null;
    registerAction('run-x', (element) => { seen = element; });
    const element = el();

    assert.equal(runAction('run-x', element), true);
    assert.equal(seen, element);
    assert.equal(runAction('run-missing', element), false);
    assert.equal(runAction('', element), false);
});

// ── built-in: toggle ──

test('toggleAction toggles the hidden class on the target(s)', () => {
    const target = el({ classes: [] });
    const restore = installDocument({ queryAll: { '#panel': [target] } });
    try {
        const trigger = el({ attrs: { 'data-toggle': '#panel' } });
        toggleAction(trigger);
        assert.equal(target.classList.contains('hidden'), true);
        toggleAction(trigger);
        assert.equal(target.classList.contains('hidden'), false);
    } finally {
        restore();
    }
});

test('toggleAction flips aria-expanded when present and prefers data-target', () => {
    const target = el({ classes: ['hidden'] });
    const restore = installDocument({ queryAll: { '#menu': [target] } });
    try {
        const trigger = el({ attrs: { 'data-action': 'toggle', 'data-target': '#menu', 'aria-expanded': 'false' } });
        toggleAction(trigger);
        assert.equal(target.classList.contains('hidden'), false);
        assert.equal(trigger.getAttribute('aria-expanded'), 'true');
    } finally {
        restore();
    }
});

// ── built-in: copy ──

// navigator is a read-only global in Node — override via defineProperty.
function withNavigator(nav, fn) {
    const prev = Object.getOwnPropertyDescriptor(globalThis, 'navigator');
    Object.defineProperty(globalThis, 'navigator', { value: nav, configurable: true, writable: true });
    try {
        return fn();
    } finally {
        if (prev) Object.defineProperty(globalThis, 'navigator', prev);
        else delete globalThis.navigator;
    }
}

test('copyAction writes data-copy to the clipboard', async () => {
    let written = null;
    await withNavigator(
        { clipboard: { writeText: (v) => { written = v; return Promise.resolve(); } } },
        () => copyAction(el({ attrs: { 'data-copy': 'hello' } })),
    );
    assert.equal(written, 'hello');
});

test('copyAction falls back to element text and no-ops without a clipboard', async () => {
    let written = null;
    await withNavigator(
        { clipboard: { writeText: (v) => { written = v; return Promise.resolve(); } } },
        () => copyAction(el({ text: '  from-text  ' })),
    );
    assert.equal(written, 'from-text');

    // No clipboard → returns undefined, does not throw.
    withNavigator({}, () => {
        assert.doesNotThrow(() => copyAction(el({ attrs: { 'data-copy': 'x' } })));
    });
});

// ── initActions ──

test('initActions registers the built-ins and is idempotent', () => {
    const restore = installDocument({});
    try {
        initActions();
        initActions(); // second call must not throw or double-wire
        assert.equal(hasAction('toggle'), true);
        assert.equal(hasAction('copy'), true);
    } finally {
        restore();
    }
});
