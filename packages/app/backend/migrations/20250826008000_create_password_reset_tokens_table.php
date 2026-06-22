<?php

declare(strict_types=1);

use Handlr\Database\Migrations\BaseMigration;

class Migration_20250826008000_CreatePasswordResetTokensTable extends BaseMigration
{
    public function up(): void
    {
        $sql = <<<'SQL'
            CREATE TABLE `password_reset_tokens` (
                `id` BINARY(16) NOT NULL PRIMARY KEY,
                `user_id` BINARY(16) NOT NULL,
                `token` VARCHAR(64) NOT NULL,
                `expires_at` DATETIME NOT NULL,
                `used_at` DATETIME DEFAULT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE INDEX `idx_token` (`token`),
                INDEX `idx_user_id` (`user_id`),
                CONSTRAINT `fk_password_reset_user`
                    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_0900_ai_ci;
        SQL;
        $this->db->execute($sql);
    }

    public function down(): void
    {
        $this->db->execute("DROP TABLE IF EXISTS `password_reset_tokens`;");
    }
}
