<?php

declare(strict_types=1);

namespace App\Auth\Domain;

use Handlr\Database\Record;

/**
 * @property int|null $permission_id
 * @property int|null $role_id
 * @property string|null $created_at
 */
class PermissionRoleRecord extends Record
{
    protected bool $useUuid = false;
    protected array $casts = [
        'permission_id' => 'int',
        'role_id' => 'int',
        'created_at' => 'date',
    ];
}
