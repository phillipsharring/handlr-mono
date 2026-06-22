<?php

declare(strict_types=1);

namespace Handlr\Ab\Domain;

use Handlr\Handlers\HandlerInput;
use Handlr\Validation\Validator;

class AbEventCapturedEvent implements HandlerInput
{
    public string $session_id;
    public string $event;
    public array $assignments;

    public function __construct(array $body = [], ?Validator $validator = null)
    {
        $this->session_id = $body['session_id'] ?? '';
        $this->event = $body['event'] ?? '';
        $this->assignments = $body['assignments'] ?? [];
    }
}
