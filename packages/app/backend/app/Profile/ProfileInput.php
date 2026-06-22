<?php

declare(strict_types=1);

namespace App\Profile;

use Handlr\Handlers\HandlerInput;
use Handlr\Validation\ValidatesRules;
use Handlr\Validation\Validator;

class ProfileInput implements HandlerInput
{
    use ValidatesRules;

    public string $user_id;
    public string $username;
    public string $email;
    public string $current_password;
    public string $new_password;
    public string $new_password_confirmation;

    public function __construct(private array $body = [], private ?Validator $validator = null)
    {
        $this->user_id = $this->body['user_id'] ?? '';
        $this->username = $this->body['username'] ?? '';
        $this->email = $this->body['email'] ?? '';
        $this->current_password = $this->body['current_password'] ?? '';
        $this->new_password = $this->body['new_password'] ?? '';
        $this->new_password_confirmation = $this->body['new_password_confirmation'] ?? '';
    }

    protected function getValidator(): Validator
    {
        return $this->validator;
    }

    protected function getBody(): array
    {
        return $this->body;
    }

    public function validateUsername(): array
    {
        return $this->runValidation([
            'user_id' => ['required', 'uuid'],
            'username' => ['required', 'string|trim,min:3,max:30'],
        ]);
    }

    public function validateEmail(): array
    {
        return $this->runValidation([
            'user_id' => ['required', 'uuid'],
            'email' => ['required', 'string|trim,min:1,max:255'],
            'current_password' => ['required', 'string|min:1'],
        ]);
    }

    public function validatePassword(): array
    {
        return $this->runValidation([
            'user_id' => ['required', 'uuid'],
            'current_password' => ['required', 'string|min:1'],
            'new_password' => ['required', 'string|min:8,max:255', 'confirmed'],
        ]);
    }
}
