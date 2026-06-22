<?php

declare(strict_types=1);

namespace App\Examples;

use Handlr\Api\Presenter;
use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Pipes\Pipe;

class HelloPipe implements Pipe
{
    public function __construct(private Presenter $presenter) {}

    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        return $response->withJson(
            $this->presenter->success('Hello, World!')
        );
    }
}
