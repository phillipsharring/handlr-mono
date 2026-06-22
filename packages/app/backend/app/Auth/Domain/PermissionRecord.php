<?php

declare(strict_types=1);

namespace App\Auth\Domain;

use Handlr\Database\Record;

/**
 * @property string|null $name
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class PermissionRecord extends Record
{
    protected bool $useUuid = false;
    protected array $casts = [
        'created_at' => 'date',
        'updated_at' => 'date',
    ];
}
