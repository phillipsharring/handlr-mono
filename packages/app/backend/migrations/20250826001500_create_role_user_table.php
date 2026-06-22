<?php

declare(strict_types=1);

use Handlr\Database\Migrations\BaseMigration;

class Migration_20250826001500_CreateRoleUserTable extends BaseMigration
{
    public function up(): void
    {
        $sql = <<<'SQL'
            CREATE TABLE IF NOT EXISTS `role_user` (
                `role_id` INT UNSIGNED NOT NULL,
                `user_id` BINARY(16) NOT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`role_id`, `user_id`),
                CONSTRAINT `fk_role_user_role_id` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_role_user_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_0900_ai_ci;
        SQL;
        $this->exec($sql);
    }

    public function down(): void
    {
        $this->exec('DROP TABLE IF EXISTS `role_user`;');
    }
}
