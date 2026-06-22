<?php

declare(strict_types=1);

use Handlr\Database\Migrations\BaseMigration;

class Migration_20250826001000_CreateRolesTable extends BaseMigration
{
    public function up(): void
    {
        $sql = <<<'SQL'
            CREATE TABLE IF NOT EXISTS `roles` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL UNIQUE,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_0900_ai_ci;
        SQL;
        $this->exec($sql);
    }

    public function down(): void
    {
        $this->exec('DROP TABLE IF EXISTS `roles`;');
    }
}
