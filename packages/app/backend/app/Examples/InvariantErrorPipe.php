<?php

declare(strict_types=1);

namespace App\Examples;

use Handlr\Api\Presenter;
use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Pipes\Pipe;

class InvariantErrorPipe implements Pipe
{
    public function __construct(private Presenter $presenter) {}

    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        // Always return an invariant error (business rule violation) for demonstration
        return $response->withStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->withJson($this->presenter->invariantError(
                'This action cannot be performed because the resource is currently locked.'
            ));
    }
}
