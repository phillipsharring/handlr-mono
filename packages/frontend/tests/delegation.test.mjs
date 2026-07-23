import { test } from 'node:test';
import assert from 'node:assert/strict';

import { onClick, onHtmx, onEvent, __resetDelegationForTest } from '../src/core/delegation.js';
import { installDocument, fakeBody, el, clickEvent, htmxEvent } from './dom-stub.mjs';

test('onClick dispatches to the matched ancestor element', () => {
    const body = fakeBody();
    const restore = installDocument({ body });
    __resetDelegationForTest();
    try {
        let got = null;
        onClick('[data-action]', (element) => { got = element; });

        const match = el({ attrs: { 'data-action': 'x' } });
        body._emit('click', clickEvent('[data-action]', match));

        assert.equal(got, match);
    } finally {
        restore();
    }
});

test('onClick does not fire when nothing matches the selector', () => {
    const body = fakeBody();
    const restore = installDocument({ body });
    __resetDelegationForTest();
    try {
        let calls = 0;
        onClick('[data-action]', () => { calls++; });

        body._emit('click', clickEvent('[data-action]', null)); // closest → null

        assert.equal(calls, 0);
    } finally {
        restore();
    }
});

test('one body listener is shared across multiple registrations for an event type', () => {
    const body = fakeBody();
    let addCount = 0;
    const countingBody = {
        addEventListener: (...args) => { addCount++; body.addEventListener(...args); },
        _emit: body._emit.bind(body),
    };
    const restore = installDocument({ body: countingBody });
    __resetDelegationForTest();
    try {
        onClick('[data-a]', () => {});
        onClick('[data-b]', () => {});
        onEvent('click', '[data-c]', () => {});
        assert.equal(addCount, 1, 'only one click listener attached to body');
    } finally {
        restore();
    }
});

test('onHtmx dispatches against the requesting element (detail.elt)', () => {
    const body = fakeBody();
    const restore = installDocument({ body });
    __resetDelegationForTest();
    try {
        let got = null;
        let seenEvent = null;
        onHtmx('htmx:afterRequest', '[data-on-success]', (element, event) => {
            got = element;
            seenEvent = event;
        });

        const match = el({ attrs: { 'data-on-success': 'redirect' } });
        const evt = htmxEvent('[data-on-success]', match, { successful: true });
        body._emit('htmx:afterRequest', evt);

        assert.equal(got, match);
        assert.equal(seenEvent, evt);
    } finally {
        restore();
    }
});
