<?php

declare(strict_types=1);

namespace App\Auth\Domain;

use Handlr\Database\Record;

/**
 * @property int|null $role_id
 * @property string|null $user_id
 * @property string|null $created_at
 */
class RoleUserRecord extends Record
{
    protected bool $useUuid = false;
    protected array $uuidColumns = ['user_id'];
    protected array $casts = [
        'role_id' => 'int',
        'created_at' => 'date',
    ];
}
