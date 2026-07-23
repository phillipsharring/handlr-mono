import { test } from 'node:test';
import assert from 'node:assert/strict';

import { logoutAction, initAuthFlows } from '../src/core/auth-flows.js';
import { hasAction, getAction } from '../src/core/actions.js';
import { el } from './dom-stub.mjs';

// Install fake fetch + window; return a restore fn and a record of the fetch call.
function installNet({ reject = false } = {}) {
    const prevFetch = globalThis.fetch;
    const prevWindow = globalThis.window;
    const record = { calls: [] };
    globalThis.fetch = (url, opts) => {
        record.calls.push({ url, opts });
        return reject ? Promise.reject(new Error('boom')) : Promise.resolve({});
    };
    globalThis.window = { location: {} };
    return {
        record,
        window: globalThis.window,
        restore() { globalThis.fetch = prevFetch; globalThis.window = prevWindow; },
    };
}

test('logoutAction GETs the default endpoint and navigates home', async () => {
    const net = installNet();
    try {
        let prevented = false;
        await logoutAction(el(), { preventDefault: () => { prevented = true; } });
        assert.equal(prevented, true, 'default anchor nav suppressed');
        assert.equal(net.record.calls.length, 1);
        assert.equal(net.record.calls[0].url, '/api/auth/logout');
        assert.equal(net.record.calls[0].opts.method, 'GET');
        assert.equal(net.record.calls[0].opts.credentials, 'same-origin');
        assert.equal(net.window.location.href, '/');
    } finally {
        net.restore();
    }
});

test('logoutAction honors data-logout-url / data-redirect-url overrides', async () => {
    const net = installNet();
    try {
        const trigger = el({ attrs: { 'data-logout-url': '/api/session/end', 'data-redirect-url': '/bye' } });
        await logoutAction(trigger, null);
        assert.equal(net.record.calls[0].url, '/api/session/end');
        assert.equal(net.window.location.href, '/bye');
    } finally {
        net.restore();
    }
});

test('logoutAction still navigates home when the request fails', async () => {
    const net = installNet({ reject: true });
    try {
        await logoutAction(el(), null);
        assert.equal(net.window.location.href, '/');
    } finally {
        net.restore();
    }
});

test('initAuthFlows registers the logout action and is idempotent', () => {
    initAuthFlows();
    initAuthFlows();
    assert.equal(hasAction('logout'), true);
    assert.equal(getAction('logout'), logoutAction);
});
