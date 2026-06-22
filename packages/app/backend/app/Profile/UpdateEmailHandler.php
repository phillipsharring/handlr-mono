<?php

declare(strict_types=1);

namespace App\Profile;

use App\Users\Data\UsersTable;
use App\Users\Domain\UserRecord;
use Handlr\Handlers\Handler;
use Handlr\Handlers\HandlerInput;
use Handlr\Handlers\HandlerResult;

class UpdateEmailHandler implements Handler
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

        $existing = $this->table->findFirst([], ['email' => $input->email]);
        if ($existing && $existing->id !== $user->id) {
            return $this->result->fail(['A user with this email already exists.']);
        }

        $user->email = $input->email;
        $this->table->update($user);

        return $this->result->ok($user);
    }
}
