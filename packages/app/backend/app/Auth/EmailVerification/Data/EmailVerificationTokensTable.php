<?php

declare(strict_types=1);

namespace App\Auth\EmailVerification\Data;

use Handlr\Database\Table;

class EmailVerificationTokensTable extends Table
{
    protected string $tableName = 'email_verification_tokens';
    protected string $recordClass = EmailVerificationTokenRecord::class;
}
