<?php

declare(strict_types=1);

namespace Handlr\Resolution;

use Handlr\Database\Record;
use Handlr\Database\Table;

/**
 * Default Resolver: a thin `findById()`-or-throw over a Table gateway.
 */
final class TableResolver implements Resolver
{
    /**
     * @template T of Record
     *
     * @param Table<T>   $table
     * @param int|string $id
     *
     * @return T
     *
     * @throws RecordNotFound
     */
    public function resolve(Table $table, int|string $id): Record
    {
        $record = $table->findById($id);

        if ($record === null) {
            throw new RecordNotFound(
                sprintf('No %s found for id "%s".', $table::class, (string) $id)
            );
        }

        return $record;
    }
}
