<?php

declare(strict_types=1);

namespace App\Auth\Data;

use App\Auth\Domain\RoleRecord;
use Handlr\Database\Table;

class RolesTable extends Table
{
    protected string $tableName = 'roles';
    protected string $recordClass = RoleRecord::class;
}
