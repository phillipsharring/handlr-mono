<?php

declare(strict_types=1);

namespace App\Auth\PasswordReset;

use App\Auth\PasswordReset\Data\PasswordResetTokenRecord;
use App\Auth\PasswordReset\Data\PasswordResetTokensTable;
use App\Users\Data\UsersTable;
use Handlr\Config\Config;
use Handlr\Handlers\Handler;
use Handlr\Handlers\HandlerInput;
use Handlr\Handlers\HandlerResult;
use Handlr\Mail\Mailer;

class ForgotPasswordHandler implements Handler
{
    public function __construct(
        private readonly UsersTable $users,
        private readonly PasswordResetTokensTable $tokens,
        private readonly Mailer $mailer,
        private readonly Config $config,
        private readonly HandlerResult $result
    ) {}

    public function handle(array|HandlerInput $input): ?HandlerResult
    {
        $user = $this->users->findFirst([], ['email' => $input->email]);

        // Always return success — don't reveal whether the email exists
        if (!$user) {
            return $this->result->ok();
        }

        // Generate a secure random token
        $token = bin2hex(random_bytes(32));

        // Store the token
        $record = new PasswordResetTokenRecord([
            'user_id'    => $user->id,
            'token'      => $token,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
        ]);
        $this->tokens->insert($record);

        // Send the email
        $appUrl = rtrim($this->config->get('app.url', 'http://localhost:5173'), '/');

        $this->mailer->send(
            $this->mailer->message()
                ->to($user->email)
                ->subject('Reset Your Password')
                ->view('emails/password-reset', [
                    'name' => $user->name,
                    'resetUrl' => $appUrl . '/reset-password/?token=' . $token,
                    'appName' => $this->config->get('app.name', 'Handlr App'),
                    'subject' => 'Reset Your Password',
                ])
        );

        return $this->result->ok();
    }
}
