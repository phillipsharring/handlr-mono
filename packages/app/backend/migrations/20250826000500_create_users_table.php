<?php

declare(strict_types=1);

use Handlr\Database\Migrations\BaseMigration;

class Migration_20250826000500_CreateUsersTable extends BaseMigration
{
    public function up(): void
    {
        $sql = <<<'SQL'
            CREATE TABLE `users` (
                `id` BINARY(16) NOT NULL PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL,
                `username` VARCHAR(30) NOT NULL DEFAULT '',
                `email` VARCHAR(255) UNIQUE NOT NULL,
                `password` VARCHAR(64) NOT NULL,
                `remember_token` VARCHAR(100) NULL,
                `last_login_at` DATETIME DEFAULT NULL,
                `email_verified_at` DATETIME DEFAULT NULL,
                `blocked_at` DATETIME DEFAULT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_0900_ai_ci;
        SQL;
        $this->db->execute($sql);
    }

    public function down(): void
    {
        $this->db->execute("DROP TABLE IF EXISTS `users`;");
    }
}
