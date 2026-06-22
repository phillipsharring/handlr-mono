<?php

declare(strict_types=1);

namespace App\Users\Inputs;

use Handlr\Handlers\HandlerInput;
use Handlr\Validation\ValidatesRules;
use Handlr\Validation\Validator;

class UserInput implements HandlerInput
{
    use ValidatesRules;

    public string $id;
    public string $name;
    public string $email;
    public ?string $password = null;

    public function __construct(private array $body = [], private ?Validator $validator = null)
    {
        $this->id = $this->body['id'] ?? '';
        $this->name = $this->body['name'] ?? '';
        $this->email = $this->body['email'] ?? '';
        $this->password = $this->body['password'] ?? null;
    }

    protected function getValidator(): Validator
    {
        return $this->validator;
    }

    protected function getBody(): array
    {
        return $this->body;
    }

    public function validateForCreate(): array
    {
        return $this->runValidation([
            'name' => ['required', 'string|trim,min:1,max:255'],
            'email' => ['required', 'string|trim,min:1,max:255'],
            'password' => ['required', 'string|min:8,max:255'],
        ]);
    }

    public function validateForUpdate(): array
    {
        return $this->runValidation([
            'id' => ['required', 'uuid'],
            'name' => ['required', 'string|trim,min:1,max:255'],
            'email' => ['required', 'string|trim,min:1,max:255'],
        ]);
    }

    public function validateId(): array
    {
        return $this->runValidation([
            'id' => ['required', 'uuid'],
        ]);
    }
}
