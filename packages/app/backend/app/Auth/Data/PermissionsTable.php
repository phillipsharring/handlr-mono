<?php

declare(strict_types=1);

namespace App\Auth\Data;

use App\Auth\Domain\PermissionRecord;
use Handlr\Database\Table;

class PermissionsTable extends Table
{
    protected string $tableName = 'permissions';
    protected string $recordClass = PermissionRecord::class;
}
