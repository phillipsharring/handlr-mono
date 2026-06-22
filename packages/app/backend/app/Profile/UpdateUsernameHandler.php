<?php

declare(strict_types=1);

namespace App\Profile;

use App\Users\Data\UsersTable;
use App\Users\Domain\UserRecord;
use App\Users\ReservedUsernames;
use Handlr\Handlers\Handler;
use Handlr\Handlers\HandlerInput;
use Handlr\Handlers\HandlerResult;

class UpdateUsernameHandler implements Handler
{
    public function __construct(
        private UsersTable $table,
        private ReservedUsernames $reservedUsernames,
        private HandlerResult $result,
    ) {}

    public function handle(ProfileInput|HandlerInput|array $input): ?HandlerResult
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $input->username)) {
            return $this->result->fail(['Username may only contain letters, numbers, and underscores.']);
        }

        if ($this->reservedUsernames->isReserved($input->username)) {
            return $this->result->fail(['This username is not available.']);
        }

        /** @var UserRecord|null $user */
        $user = $this->table->findById($input->user_id);
        if (!$user) {
            return $this->result->fail(['User not found.']);
        }

        $existing = $this->table->findFirst([], ['username' => $input->username]);
        if ($existing && $existing->id !== $user->id) {
            return $this->result->fail(['This username is already taken.']);
        }

        $user->username = $input->username;
        $this->table->update($user);

        return $this->result->ok($user);
    }
}
