<?php

declare(strict_types=1);

namespace App\Auth\EmailVerification;

use App\Auth\EmailVerification\Data\EmailVerificationTokensTable;
use App\Users\Data\UsersTable;
use Handlr\Handlers\Handler;
use Handlr\Handlers\HandlerInput;
use Handlr\Handlers\HandlerResult;

class VerifyEmailHandler implements Handler
{
    public function __construct(
        private readonly UsersTable $users,
        private readonly EmailVerificationTokensTable $tokens,
        private readonly HandlerResult $result,
    ) {}

    public function handle(array|HandlerInput $input): ?HandlerResult
    {
        $tokenRecord = $this->tokens->findFirst([], ['token' => $input->token]);

        if (!$tokenRecord) {
            return $this->result->fail(['This verification link is invalid.']);
        }

        if ($tokenRecord->used_at !== null) {
            return $this->result->fail(['This verification link has already been used.']);
        }

        if (strtotime($tokenRecord->expires_at) < time()) {
            return $this->result->fail(['This verification link has expired. Please request a new one.']);
        }

        $user = $this->users->findById($tokenRecord->user_id);

        if (!$user) {
            return $this->result->fail(['This verification link is invalid.']);
        }

        // Already verified (e.g., clicked link twice)
        if ($user->email_verified_at !== null) {
            $tokenRecord->used_at = date('Y-m-d H:i:s');
            $this->tokens->update($tokenRecord);
            return $this->result->ok();
        }

        $user->email_verified_at = date('Y-m-d H:i:s');
        $this->users->update($user);

        $tokenRecord->used_at = date('Y-m-d H:i:s');
        $this->tokens->update($tokenRecord);

        return $this->result->ok();
    }
}
