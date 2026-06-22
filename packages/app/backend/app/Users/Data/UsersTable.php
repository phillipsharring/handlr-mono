<?php

declare(strict_types=1);

namespace App\Users\Data;

use App\Users\Domain\UserRecord;
use Handlr\Database\Table;

class UsersTable extends Table
{
    protected string $tableName = 'users';
    protected string $recordClass = UserRecord::class;
}
