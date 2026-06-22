<?php

declare(strict_types=1);

namespace App\Auth\PasswordReset;

use App\Auth\PasswordReset\Data\PasswordResetTokensTable;
use App\Users\Data\UsersTable;
use Handlr\Handlers\Handler;
use Handlr\Handlers\HandlerInput;
use Handlr\Handlers\HandlerResult;

class ResetPasswordHandler implements Handler
{
    public function __construct(
        private readonly UsersTable $users,
        private readonly PasswordResetTokensTable $tokens,
        private readonly HandlerResult $result,
    ) {}

    public function handle(array|HandlerInput $input): ?HandlerResult
    {
        // Find the token
        $tokenRecord = $this->tokens->findFirst([], ['token' => $input->token]);

        if (!$tokenRecord) {
            return $this->result->fail(['This reset link is invalid.']);
        }

        // Check if already used
        if ($tokenRecord->used_at !== null) {
            return $this->result->fail(['This reset link has already been used.']);
        }

        // Check if expired
        if (strtotime($tokenRecord->expires_at) < time()) {
            return $this->result->fail(['This reset link has expired. Please request a new one.']);
        }

        // Find the user
        $user = $this->users->findById($tokenRecord->user_id);

        if (!$user) {
            return $this->result->fail(['This reset link is invalid.']);
        }

        // Update the password
        $user->password = password_hash($input->password, PASSWORD_BCRYPT, ['cost' => 12]);
        $this->users->update($user);

        // Mark the token as used
        $tokenRecord->used_at = date('Y-m-d H:i:s');
        $this->tokens->update($tokenRecord);

        return $this->result->ok();
    }
}
