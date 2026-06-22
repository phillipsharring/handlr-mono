<?php

declare(strict_types=1);

namespace App\Auth\Login\Domain\Events;

use Handlr\Handlers\HandlerInput;
use Handlr\Validation\Validator;

class UserLoggedInEvent implements HandlerInput
{
    public string $user_id;
    public string $logged_in_at;

    public function __construct(array $body = [], ?Validator $validator = null)
    {
        $this->user_id = $body['user_id'] ?? '';
        $this->logged_in_at = $body['logged_in_at'] ?? date('Y-m-d H:i:s');
    }
}
