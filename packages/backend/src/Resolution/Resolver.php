<?php

declare(strict_types=1);

namespace Handlr\Resolution;

use Handlr\Database\Record;
use Handlr\Database\Table;

/**
 * Locates one record by primary key, or fails loudly.
 *
 * This is the "resolve" half of route resolution: turn a `{id}` route param
 * into the record it names. It performs no authorization — that is the job of a
 * Policy consulted after the record is in hand. It also does not filter by
 * state (e.g. soft-deletes); that is an Invariant/Policy concern.
 *
 * Pass the DI'd Table instance for the record type; the return is typed to that
 * table's record when the Table is annotated `@extends Table<SomeRecord>`.
 */
interface Resolver
{
    /**
     * @template T of Record
     *
     * @param Table<T>   $table The table gateway for the record type.
     * @param int|string $id    The primary key, usually a route param.
     *
     * @return T The located record.
     *
     * @throws RecordNotFound When no row matches the id.
     */
    public function resolve(Table $table, int|string $id): Record;
}
