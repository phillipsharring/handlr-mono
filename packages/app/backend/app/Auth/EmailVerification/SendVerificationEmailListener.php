<?php

declare(strict_types=1);

namespace App\Auth\EmailVerification;

use App\Auth\EmailVerification\Data\EmailVerificationTokenRecord;
use App\Auth\EmailVerification\Data\EmailVerificationTokensTable;
use App\Users\Data\UsersTable;
use Handlr\Config\Config;
use Handlr\Handlers\Handler;
use Handlr\Handlers\HandlerInput;
use Handlr\Handlers\HandlerResult;
use Handlr\Mail\Mailer;

class SendVerificationEmailListener implements Handler
{
    public function __construct(
        private readonly UsersTable $users,
        private readonly EmailVerificationTokensTable $tokens,
        private readonly Mailer $mailer,
        private readonly Config $config,
    ) {}

    public function handle(array|HandlerInput $input): ?HandlerResult
    {
        $user = $this->users->findById($input->user_id);
        if (!$user) return null;

        // Don't send if already verified
        if ($user->email_verified_at !== null) return null;

        $token = bin2hex(random_bytes(32));

        $record = new EmailVerificationTokenRecord([
            'user_id' => $user->id,
            'token' => $token,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours')),
        ]);
        $this->tokens->insert($record);

        $appUrl = rtrim($this->config->get('app.url', 'http://localhost:5173'), '/');

        $this->mailer->send(
            $this->mailer->message()
                ->to($user->email)
                ->subject('Verify Your Email')
                ->view('emails/verify-email', [
                    'name' => $user->name,
                    'verifyUrl' => $appUrl . '/verify-email/?token=' . $token,
                    'appName' => $this->config->get('app.name', 'Handlr App'),
                    'subject' => 'Verify Your Email',
                ])
        );

        return null;
    }
}
