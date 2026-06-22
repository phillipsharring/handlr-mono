<?php

declare(strict_types=1);

use App\Users\Data\UsersTable;

return [
    UsersTable::class => [
        [
            'id' => '019bca8d-283f-7073-9288-52bd18413752',
            'name' => 'Admin',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => password_hash('password', PASSWORD_BCRYPT, ['cost' => 12]),
            'email_verified_at' => date('Y-m-d H:i:s'),
        ],
        [
            'id' => '019bca8d-283f-7073-9288-52bd18413753',
            'name' => 'User',
            'username' => 'user',
            'email' => 'user@example.com',
            'password' => password_hash('password', PASSWORD_BCRYPT, ['cost' => 12]),
            'email_verified_at' => date('Y-m-d H:i:s'),
        ],
    ],
];
