<?php

declare(strict_types=1);

namespace Handlr\Ab\Pipes;

use Handlr\Ab\Data\AbResultsQuery;
use Handlr\Api\Presenter;
use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Pipes\Pipe;

class GetAbTests implements Pipe
{
    public function __construct(
        private AbResultsQuery $query,
        private Presenter $presenter,
    ) {}

    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        $summaries = $this->query->getAllTestSummaries();

        return $response->withStatus(Response::HTTP_OK)
            ->withJson(
                $this->presenter
                    ->withData($summaries)
                    ->success()
            );
    }
}
