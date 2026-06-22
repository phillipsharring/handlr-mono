<?php

declare(strict_types=1);

use App\Auth\Data\RolesTable;
use App\Auth\Data\PermissionsTable;
use App\Auth\Data\PermissionRoleTable;
use App\Auth\Data\RoleUserTable;

return [
    RolesTable::class => [
        [
            'id' => 1,
            'name' => 'admin',
        ],
    ],

    PermissionsTable::class => [
        [
            'id' => 1,
            'name' => 'admin.access',
        ],
    ],

    PermissionRoleTable::class => [
        // admin -> admin.access
        ['permission_id' => 1, 'role_id' => 1],
    ],

    RoleUserTable::class => [
        // admin user gets admin role
        [
            'role_id' => 1,
            'user_id' => '019bca8d-283f-7073-9288-52bd18413752',
        ],
    ],
];
