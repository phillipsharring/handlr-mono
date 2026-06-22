<?php

declare(strict_types=1);

namespace Handlr\Ab\Pipes;

use Handlr\Ab\Data\AbTestsTable;
use Handlr\Api\Presenter;
use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Pipes\Pipe;

class PatchUpdateAbTest implements Pipe
{
    public function __construct(
        private AbTestsTable $testsTable,
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

        $body = $request->getParsedBody() ?? [];

        if (isset($body['status'])) {
            $allowed = ['active', 'paused', 'completed'];
            if (!in_array($body['status'], $allowed, true)) {
                return $response->withStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
                    ->withJson($this->presenter->validationError('Invalid status.'));
            }
            $test->status = $body['status'];
        }

        if (isset($body['name'])) {
            $test->name = trim($body['name']);
        }

        $this->testsTable->update($test);

        return $response->withStatus(Response::HTTP_OK)
            ->withJson($this->presenter->success('Test updated.'));
    }
}
