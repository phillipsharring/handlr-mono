import { test, mock } from 'node:test';
import assert from 'node:assert/strict';

import { debounce, scrubSearchInput } from '../src/helpers/debounce.js';

// ── scrubSearchInput (pure) ──

test('scrubSearchInput returns empty string for non-string input', () => {
    assert.equal(scrubSearchInput(null), '');
    assert.equal(scrubSearchInput(undefined), '');
    assert.equal(scrubSearchInput(42), '');
});

test('scrubSearchInput strips injection characters', () => {
    assert.equal(scrubSearchInput(`<script>'";\\`), 'script');
});

test('scrubSearchInput collapses whitespace and trims', () => {
    assert.equal(scrubSearchInput('  foo   bar  '), 'foo bar');
});

// ── debounce (uses fake timers) ──

test('debounce delays invocation until the delay elapses', () => {
    mock.timers.enable({ apis: ['setTimeout'] });
    try {
        let calls = 0;
        const fn = debounce(() => { calls++; }, 100);

        fn();
        fn();
        fn();
        assert.equal(calls, 0, 'not called before delay');

        mock.timers.tick(100);
        assert.equal(calls, 1, 'called once after delay');
    } finally {
        mock.timers.reset();
    }
});

test('debounce passes the latest arguments through', () => {
    mock.timers.enable({ apis: ['setTimeout'] });
    try {
        let seen = null;
        const fn = debounce((arg) => { seen = arg; }, 100);

        fn('a');
        fn('b');
        mock.timers.tick(100);

        assert.equal(seen, 'b');
    } finally {
        mock.timers.reset();
    }
});

test('debounce.cancel prevents a pending invocation', () => {
    mock.timers.enable({ apis: ['setTimeout'] });
    try {
        let calls = 0;
        const fn = debounce(() => { calls++; }, 100);

        fn();
        fn.cancel();
        mock.timers.tick(100);

        assert.equal(calls, 0);
    } finally {
        mock.timers.reset();
    }
});
