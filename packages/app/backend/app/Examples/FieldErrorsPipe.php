<?php

declare(strict_types=1);

namespace App\Examples;

use Handlr\Api\Presenter;
use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Pipes\Pipe;

class FieldErrorsPipe implements Pipe
{
    public function __construct(private Presenter $presenter) {}
    
    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        // Always return field validation errors for demonstration
        return $response->withStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->withJson($this->presenter->validationError(
                'Could not process your request.',
                [
                    'email' => 'Email is required and must be valid.',
                    'age' => 'Age must be at least 18.',
                ]
            ));
    }
}
