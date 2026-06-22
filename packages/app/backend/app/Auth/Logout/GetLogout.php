<?php

declare(strict_types=1);

namespace App\Auth\Logout;

use Handlr\Api\Presenter;
use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Pipes\Pipe;

class GetLogout implements Pipe
{
    public function __construct(
        private readonly LogoutHandler $handler,
        private readonly Presenter $presenter
    ) {}

    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        $this->handler->handle();

        return $response->withStatus(Response::HTTP_OK)
            ->withJson($this->presenter->success('Logged out.'));
    }
}
