<?php

declare(strict_types=1);

namespace Handlr\Database;

/**
 * The seam every table write reports through.
 *
 * `Table` calls `record()` after each insert/update/delete — but only when
 * `isRecording()` is true, so a table that isn't being recorded pays nothing more
 * than one cheap boolean check (no before-image reads, no `Change` objects built).
 *
 * The framework binds {@see NullRecorder} by default (records nothing). A feature
 * that wants the data — undo, an audit log — is a module that rebinds this interface
 * to a real recorder in its service provider's `register()`, and arms it per request
 * for the actions that opted in. The framework provides the seam; it does not decide
 * what, if anything, listens.
 */
interface ChangeRecorder
{
    /**
     * Whether writes should be captured right now. `Table` checks this *before* doing
     * any before-image work, so keep it cheap. The default recorder returns false; a
     * real one returns true only when armed for the current request/action.
     */
    public function isRecording(): bool;

    /**
     * Record one write. Called by `Table` only when `isRecording()` is true.
     */
    public function record(Change $change): void;
}
