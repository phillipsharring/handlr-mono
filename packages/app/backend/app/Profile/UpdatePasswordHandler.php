<?php

declare(strict_types=1);

namespace App\Profile;

use App\Users\Data\UsersTable;
use App\Users\Domain\UserRecord;
use Handlr\Handlers\Handler;
use Handlr\Handlers\HandlerInput;
use Handlr\Handlers\HandlerResult;

class UpdatePasswordHandler implements Handler
{
    public function __construct(
        private UsersTable $table,
        private HandlerResult $result,
    ) {}

    public function handle(ProfileInput|HandlerInput|array $input): ?HandlerResult
    {
        /** @var UserRecord|null $user */
        $user = $this->table->findById($input->user_id);
        if (!$user) {
            return $this->result->fail(['User not found.']);
        }

        if (!password_verify($input->current_password, $user->password)) {
            return $this->result->fail(['Current password is incorrect.']);
        }

        $user->password = password_hash($input->new_password, PASSWORD_BCRYPT);
        $this->table->update($user);

        return $this->result->ok($user);
    }
}
