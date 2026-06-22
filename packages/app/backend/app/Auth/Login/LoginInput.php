<?php

declare(strict_types=1);

namespace App\Auth\Login;

use Handlr\Handlers\HandlerInput;
use Handlr\Validation\ValidatesRules;
use Handlr\Validation\Validator;

class LoginInput implements HandlerInput
{
    use ValidatesRules;

    public string $email;
    public string $password;
    public bool $remember_me;

    public function __construct(private array $body = [], private ?Validator $validator = null)
    {
        $this->email = $this->body['email'] ?? '';
        $this->password = $this->body['password'] ?? '';
        $this->remember_me = !empty($this->body['remember_me']);
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
            'password' => ['required'],
        ]);
    }
}
