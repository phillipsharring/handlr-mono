<?php

declare(strict_types=1);

namespace Handlr\Resolution;

use Handlr\Database\Table;
use Handlr\Policies\Policy;
use RuntimeException;

/**
 * Maps a record type to the Table that loads it and the Policy that governs it.
 *
 * Populated once per record type (typically in a service provider's boot()), so
 * routes can name just the record + action while the framework knows how to
 * resolve and authorize it.
 *
 * ```php
 * $registry->for(ChecklistRecord::class, ChecklistsTable::class, ChecklistPolicy::class);
 * ```
 */
final class ResolutionRegistry
{
    /** @var array<class-string, array{table: class-string, policy: class-string|null}> */
    private array $map = [];

    /**
     * Register how to resolve and authorize a record type.
     *
     * @param class-string              $record The record type.
     * @param class-string<Table>       $table  Table gateway that loads it.
     * @param class-string<Policy>|null $policy Policy governing it (optional — omit
     *                                          for records resolved without a policy).
     */
    public function for(string $record, string $table, ?string $policy = null): self
    {
        $this->map[$record] = ['table' => $table, 'policy' => $policy];
        return $this;
    }

    public function has(string $record): bool
    {
        return isset($this->map[$record]);
    }

    /**
     * @param class-string $record
     *
     * @return array{table: class-string, policy: class-string|null}
     */
    public function lookup(string $record): array
    {
        return $this->map[$record]
            ?? throw new RuntimeException(
                "No resolution registered for {$record}. Call ResolutionRegistry::for()."
            );
    }
}
