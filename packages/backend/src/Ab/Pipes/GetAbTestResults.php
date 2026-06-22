<?php

declare(strict_types=1);

namespace Handlr\Ab\Pipes;

use Handlr\Ab\Data\AbResultsQuery;
use Handlr\Ab\Data\AbTestsTable;
use Handlr\Api\Presenter;
use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Pipes\Pipe;

class GetAbTestResults implements Pipe
{
    public function __construct(
        private AbTestsTable $testsTable,
        private AbResultsQuery $query,
        private Presenter $presenter,
    ) {}

    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $test = $this->testsTable->findById($id);

        if (!$test) {
            return $response->withStatus(Response::HTTP_NOT_FOUND)
                ->withJson($this->presenter->invariantError('Test not found.'));
        }

        $results = $this->query->getResultsForTest($id);

        return $response->withStatus(Response::HTTP_OK)
            ->withJson(
                $this->presenter
                    ->withSingleData([
                        'test' => [
                            'id' => $test->id,
                            'name' => $test->name,
                            'variants' => $test->getVariants(),
                            'status' => $test->status,
                            'created_at' => $test->created_at,
                        ],
                        'results' => $results,
                    ])
                    ->success()
            );
    }
}
