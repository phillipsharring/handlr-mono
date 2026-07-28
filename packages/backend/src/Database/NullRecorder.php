<?php

declare(strict_types=1);

namespace Handlr\Database;

/**
 * The default {@see ChangeRecorder}: records nothing, at zero cost. Bound by the
 * Kernel so `Table` always has something to call; a module swaps in a real recorder
 * when an app opts into undo or auditing.
 */
final class NullRecorder implements ChangeRecorder
{
    public function isRecording(): bool
    {
        return false;
    }

    public function record(Change $change): void
    {
        // Intentionally empty: nothing is recording.
    }
}
