<?php

declare(strict_types=1);

namespace App\Auth\Signup;

use Handlr\Handlers\HandlerInput;
use Handlr\Validation\ValidatesRules;
use Handlr\Validation\Validator;

class SignupInput implements HandlerInput
{
    use ValidatesRules;

    public string $username;
    public string $email;
    public string $password;
    public string $password_confirmation;

    public function __construct(private array $body = [], private ?Validator $validator = null)
    {
        $this->username = $this->body['username'] ?? '';
        $this->email = $this->body['email'] ?? '';
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
            'username' => ['required', 'string|trim,min:3,max:30'],
            'email' => ['required', 'string|trim,min:1,max:255'],
            'password' => ['required', 'string|min:8,max:255', 'confirmed'],
        ]);
    }
}
