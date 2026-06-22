<?php

declare(strict_types=1);

namespace App\Auth\EmailVerification;

use Handlr\Handlers\HandlerInput;
use Handlr\Validation\Validator;

class VerifyEmailInput implements HandlerInput
{
    public string $token;

    public function __construct(array $body = [], ?Validator $validator = null)
    {
        $this->token = trim($body['token'] ?? '');
    }
}
