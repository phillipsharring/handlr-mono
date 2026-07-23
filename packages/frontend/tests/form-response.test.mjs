import { test } from 'node:test';
import assert from 'node:assert/strict';

import {
    parseVerb,
    isRequestSuccess,
    safeParseJson,
    resolveRedirectUrl,
    runVerb,
    onFormSuccess,
} from '../src/core/form-response.js';
import { registerAction } from '../src/core/actions.js';
import { __resetDelegationForTest } from '../src/core/delegation.js';
import { installDocument, fakeBody, el, htmxEvent } from './dom-stub.mjs';

// ── parseVerb ──

test('parseVerb splits verb and arg', () => {
    assert.deepEqual(parseVerb('redirect'), { name: 'redirect', arg: null });
    assert.deepEqual(parseVerb('reveal:#ok'), { name: 'reveal', arg: '#ok' });
    assert.deepEqual(parseVerb('toast:Hello world'), { name: 'toast', arg: 'Hello world' });
    assert.deepEqual(parseVerb('action:name'), { name: 'action', arg: 'name' });
    assert.deepEqual(parseVerb('empty:'), { name: 'empty', arg: null });
    assert.deepEqual(parseVerb('   '), { name: '', arg: null });
    assert.deepEqual(parseVerb(null), { name: '', arg: null });
});

// ── isRequestSuccess ──

test('isRequestSuccess reads xhr.status, falling back to detail.successful', () => {
    assert.equal(isRequestSuccess({ xhr: { status: 200 } }), true);
    assert.equal(isRequestSuccess({ xhr: { status: 204 } }), true);
    assert.equal(isRequestSuccess({ xhr: { status: 404 } }), false);
    assert.equal(isRequestSuccess({ xhr: { status: 500 } }), false);
    assert.equal(isRequestSuccess({ successful: true }), true);
    assert.equal(isRequestSuccess({ successful: false }), false);
    assert.equal(isRequestSuccess({}), false);
});

// ── safeParseJson ──

test('safeParseJson parses JSON and returns null on failure', () => {
    assert.deepEqual(safeParseJson({ responseText: '{"a":1}' }), { a: 1 });
    assert.equal(safeParseJson({ responseText: 'not json' }), null);
    assert.equal(safeParseJson({ responseText: '' }), null);
    assert.equal(safeParseJson({}), null);
    assert.equal(safeParseJson(undefined), null);
});

// ── resolveRedirectUrl ──

test('resolveRedirectUrl prefers response meta over the data attribute', () => {
    const withAttr = el({ attrs: { 'data-redirect-url': '/fallback' } });
    assert.equal(resolveRedirectUrl(withAttr, { meta: { redirect: '/from-meta' } }), '/from-meta');
    assert.equal(resolveRedirectUrl(withAttr, null), '/fallback');
    assert.equal(resolveRedirectUrl(el(), null), null);
});

// ── runVerb ──

test('runVerb reveal hides the form and reveals the target', () => {
    const form = el({ classes: [] });
    const target = el({ classes: ['hidden'] });
    const restore = installDocument({ query: { '#ok': target } });
    try {
        const trigger = el({ attrs: { 'data-on-success': 'reveal:#ok' }, form });
        runVerb('reveal:#ok', trigger, undefined);
        assert.equal(form.classList.contains('hidden'), true);
        assert.equal(target.classList.contains('hidden'), false);
    } finally {
        restore();
    }
});

test('runVerb action runs the named action', () => {
    let ran = null;
    registerAction('verb-spy', (element) => { ran = element; });
    const trigger = el();
    runVerb('action:verb-spy', trigger, undefined);
    assert.equal(ran, trigger);
});

test('runVerb redirect navigates to the resolved url', () => {
    const prevWindow = globalThis.window;
    globalThis.window = { location: {} };
    try {
        const trigger = el({ attrs: { 'data-redirect-url': '/home' } });
        runVerb('redirect', trigger, undefined);
        assert.equal(globalThis.window.location.href, '/home');
    } finally {
        globalThis.window = prevWindow;
    }
});

test('runVerb ignores unknown verbs', () => {
    assert.doesNotThrow(() => runVerb('bogus:thing', el(), undefined));
    assert.doesNotThrow(() => runVerb('', el(), undefined));
});

// ── onFormSuccess (delegated) ──

test('onFormSuccess fires with parsed data on 2xx and skips on error', () => {
    const body = fakeBody();
    const restore = installDocument({ body });
    __resetDelegationForTest();
    try {
        const calls = [];
        onFormSuccess('[data-form]', (data, form, xhr) => { calls.push({ data, form, xhr }); });

        const match = el({ attrs: { 'data-form': '' } });
        const okXhr = { status: 200, responseText: '{"ok":true}' };
        body._emit('htmx:afterRequest', htmxEvent('[data-form]', match, { xhr: okXhr }));

        assert.equal(calls.length, 1);
        assert.deepEqual(calls[0].data, { ok: true });
        assert.equal(calls[0].form, match); // no closest form → element itself
        assert.equal(calls[0].xhr, okXhr);

        // Non-2xx must not fire.
        body._emit('htmx:afterRequest', htmxEvent('[data-form]', match, { xhr: { status: 500, responseText: '{}' } }));
        assert.equal(calls.length, 1);
    } finally {
        restore();
    }
});
