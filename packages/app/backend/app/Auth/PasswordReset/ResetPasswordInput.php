<?php

declare(strict_types=1);

namespace App\Auth\PasswordReset;

use Handlr\Handlers\HandlerInput;
use Handlr\Validation\ValidatesRules;
use Handlr\Validation\Validator;

class ResetPasswordInput implements HandlerInput
{
    use ValidatesRules;

    public string $token;
    public string $password;
    public string $password_confirmation;

    public function __construct(private array $body = [], private ?Validator $validator = null)
    {
        $this->token = trim($this->body['token'] ?? '');
        $this->password = $this->body['password'] ?? '';
        $this->password_confirmation = $this->body['password_confirmation'] ?? '';
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
            'token' => ['required', 'string'],
            'password' => ['required', 'string|min:8,max:255', 'confirmed'],
        ]);
    }
}
