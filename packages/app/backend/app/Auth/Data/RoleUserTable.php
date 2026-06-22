<?php

declare(strict_types=1);

namespace App\Auth\Data;

use App\Auth\Domain\RoleUserRecord;
use Handlr\Database\Table;

class RoleUserTable extends Table
{
    protected string $tableName = 'role_user';
    protected string $recordClass = RoleUserRecord::class;
}
