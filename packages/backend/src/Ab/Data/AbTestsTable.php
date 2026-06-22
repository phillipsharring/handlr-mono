<?php

declare(strict_types=1);

namespace Handlr\Ab\Data;

use Handlr\Ab\Domain\AbTestRecord;
use Handlr\Database\Table;

class AbTestsTable extends Table
{
    protected string $tableName = 'ab_tests';
    protected string $recordClass = AbTestRecord::class;
}
