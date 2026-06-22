<?php

declare(strict_types=1);

namespace App\Auth\Login\Listeners;

use App\Auth\Login\Domain\Events\UserLoggedInEvent;
use App\Users\Data\UsersTable;
use Handlr\Handlers\Handler;
use Handlr\Handlers\HandlerInput;
use Handlr\Handlers\HandlerResult;

class UpdateLastLoginListener implements Handler
{
    public function __construct(
        private UsersTable $users,
    ) {}

    public function handle(UserLoggedInEvent|HandlerInput|array $input): ?HandlerResult
    {
        $user = $this->users->findById($input->user_id);
        if (!$user) {
            return null;
        }

        $user->last_login_at = $input->logged_in_at;
        $this->users->update($user);

        return null;
    }
}
