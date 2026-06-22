<?php

declare(strict_types=1);

namespace App\Users\Domain;

use Handlr\Database\Record;

/**
 * @property string $name
 * @property string|null $username
 * @property string $email
 * @property string $password
 * @property string|null $remember_token
 * @property string|null $last_login_at
 * @property string|null $email_verified_at
 * @property string|null $blocked_at
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class UserRecord extends Record
{
    protected array $casts = [
        'last_login_at' => 'date',
        'email_verified_at' => 'date',
        'blocked_at' => 'date',
        'created_at' => 'date',
        'updated_at' => 'date',
    ];

    public function toArray(): array
    {
        $data = parent::toArray();
        unset($data['password'], $data['remember_token']);
        return $data;
    }
}
