<?php

declare(strict_types=1);

namespace App\Auth\Data;

use Handlr\Auth\PermissionsProviderInterface;
use Handlr\Database\Query;

class UserPermissionsQuery extends Query implements PermissionsProviderInterface
{
    /**
     * Get role names for a user.
     *
     * @return string[]
     */
    public function getRolesForUser(string $userId): array
    {
        $sql = <<<'SQL'
            SELECT `r`.`name`
            FROM `roles` AS `r`
            INNER JOIN `role_user` AS `ru`
            ON `ru`.`role_id` = `r`.`id`
            WHERE `ru`.`user_id` = ?
        SQL;

        return $this->column($sql, [$this->uuidToBin($userId)]) ?: [];
    }

    /**
     * Get distinct permission names for a user (via roles).
     *
     * @return string[]
     */
    public function getPermissionsForUser(string $userId): array
    {
        $sql = <<<'SQL'
            SELECT DISTINCT `p`.`name`
            FROM `permissions` AS `p`
            INNER JOIN `permission_role` AS `pr`
              ON `pr`.`permission_id` = `p`.`id`
            INNER JOIN `role_user` AS `ru`
              ON `ru`.`role_id` = `pr`.`role_id`
            WHERE `ru`.`user_id` = ?
        SQL;

        return $this->column($sql, [$this->uuidToBin($userId)]) ?: [];
    }
}
