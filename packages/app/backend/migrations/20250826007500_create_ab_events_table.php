<?php

declare(strict_types=1);

use Handlr\Database\Migrations\BaseMigration;

class Migration_20250826007500_CreateAbEventsTable extends BaseMigration
{
    public function up(): void
    {
        $sql = <<<'SQL'
            CREATE TABLE `ab_events` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `ab_test_id` INT NOT NULL,
                `variant` VARCHAR(20) NOT NULL,
                `session_id` VARCHAR(128) NOT NULL,
                `event` VARCHAR(100) NOT NULL,
                `event_date` DATE NOT NULL DEFAULT (CURDATE()),
                `count` INT NOT NULL DEFAULT 1,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT `fk_ab_events_test_id`
                    FOREIGN KEY (`ab_test_id`) REFERENCES `ab_tests` (`id`) ON DELETE CASCADE,
                UNIQUE KEY `uq_ab_events_aggregate` (`ab_test_id`, `variant`, `session_id`, `event`, `event_date`),
                INDEX `idx_ab_events_test_variant` (`ab_test_id`, `variant`),
                INDEX `idx_ab_events_session` (`session_id`)
            ) ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_0900_ai_ci;
        SQL;

        $this->db->execute($sql);
    }

    public function down(): void
    {
        $this->db->execute("DROP TABLE IF EXISTS `ab_events`;");
    }
}
