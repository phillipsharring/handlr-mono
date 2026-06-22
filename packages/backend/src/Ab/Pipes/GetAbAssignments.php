<?php

declare(strict_types=1);

namespace Handlr\Ab\Pipes;

use Handlr\Ab\AbService;
use Handlr\Api\Presenter;
use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Pipes\Pipe;

class GetAbAssignments implements Pipe
{
    public function __construct(
        private AbService $abService,
        private Presenter $presenter,
    ) {}

    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        $sessionId = session_id();

        return $response->withStatus(Response::HTTP_OK)
            ->withJson(
                $this->presenter
                    ->withSingleData([
                        'assignments' => $this->abService->getAssignments($sessionId),
                    ])
                    ->success()
            );
    }
}
