<?php

declare(strict_types=1);

namespace App\Auth\Data;

use App\Auth\Domain\PermissionRoleRecord;
use Handlr\Database\Table;

class PermissionRoleTable extends Table
{
    protected string $tableName = 'permission_role';
    protected string $recordClass = PermissionRoleRecord::class;
}
