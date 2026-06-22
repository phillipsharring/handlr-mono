<?php

declare(strict_types=1);

namespace App\Auth\Signup\Domain\Events;

use Handlr\Handlers\HandlerInput;
use Handlr\Validation\Validator;

class UserSignedUpEvent implements HandlerInput
{
    public string $user_id;

    public function __construct(array $body = [], ?Validator $validator = null)
    {
        $this->user_id = $body['user_id'] ?? '';
    }
}
