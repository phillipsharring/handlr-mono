<?php

declare(strict_types=1);

namespace App\Auth\PasswordReset;

use Handlr\Handlers\HandlerInput;
use Handlr\Validation\ValidatesRules;
use Handlr\Validation\Validator;

class ForgotPasswordInput implements HandlerInput
{
    use ValidatesRules;

    public string $email;

    public function __construct(private array $body = [], private ?Validator $validator = null)
    {
        $this->email = strtolower(trim($this->body['email'] ?? ''));
    }

    protected function getValidator(): Validator
    {
        return $this->validator;
    }

    protected function getBody(): array
    {
        return $this->body;
    }

    public function validate(): array
    {
        return $this->runValidation([
            'email' => ['required', 'email'],
        ]);
    }
}
