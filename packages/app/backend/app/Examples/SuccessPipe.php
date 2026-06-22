<?php

declare(strict_types=1);

namespace App\Examples;

use Handlr\Api\Presenter;
use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Pipes\Pipe;

class SuccessPipe implements Pipe
{
    public function __construct(private Presenter $presenter) {}

    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        // Always return success for demonstration
        return $response->withStatus(Response::HTTP_OK)
            ->withJson($this->presenter->success(
                'Form submitted successfully!'
            ));
    }
}
