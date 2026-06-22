<?php

declare(strict_types=1);

namespace App\Auth\EmailVerification\Data;

use Handlr\Database\Record;

/**
 * @property string $user_id
 * @property string $token
 * @property string $expires_at
 * @property string|null $used_at
 * @property string $created_at
 */
class EmailVerificationTokenRecord extends Record
{
    protected array $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'created_at' => 'datetime',
    ];
    protected array $uuidColumns = ['user_id'];
}
