<?php

declare(strict_types=1);

namespace App\Auth\PasswordReset\Data;

use Handlr\Database\Table;

class PasswordResetTokensTable extends Table
{
    protected string $tableName = 'password_reset_tokens';
    protected string $recordClass = PasswordResetTokenRecord::class;
}
