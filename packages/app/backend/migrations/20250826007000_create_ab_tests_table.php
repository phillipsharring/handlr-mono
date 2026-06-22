<?php

declare(strict_types=1);

use Handlr\Database\Migrations\BaseMigration;

class Migration_20250826007000_CreateAbTestsTable extends BaseMigration
{
    public function up(): void
    {
        $sql = <<<'SQL'
            CREATE TABLE `ab_tests` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(100) NOT NULL UNIQUE,
                `variants` JSON NOT NULL DEFAULT ('["a","b"]'),
                `status` ENUM('active', 'paused', 'completed') NOT NULL DEFAULT 'active',
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
        $this->db->execute("DROP TABLE IF EXISTS `ab_tests`;");
    }
}
