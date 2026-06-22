<?php

declare(strict_types=1);

namespace App\Users;

class ReservedUsernames
{
    private const RESERVED = [
        'admin',
        'administrator',
        'api',
        'billing',
        'contact',
        'dashboard',
        'enterprise',
        'help',
        'info',
        'login',
        'logout',
        'marketing',
        'mod',
        'moderator',
        'null',
        'pricing',
        'profile',
        'root',
        'search',
        'settings',
        'signup',
        'staff',
        'status',
        'support',
        'sysadmin',
        'system',
        'test',
        'undefined',
        'user',
        'users',
        'webmaster',
        'www',
    ];

    public function isReserved(string $username): bool
    {
        return in_array(strtolower(trim($username)), self::RESERVED, true);
    }
}
