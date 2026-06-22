<?php

declare(strict_types=1);

namespace Handlr\Ab\Pipes;

use Handlr\Ab\Data\AbTestsTable;
use Handlr\Ab\Domain\AbTestRecord;
use Handlr\Api\Presenter;
use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Pipes\Pipe;

class PostCreateAbTest implements Pipe
{
    public function __construct(
        private AbTestsTable $testsTable,
        private Presenter $presenter,
    ) {}

    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        $body = $request->getParsedBody() ?? [];
        $name = trim($body['name'] ?? '');
        $variants = $body['variants'] ?? ['a', 'b'];

        if (!$name) {
            return $response->withStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
                ->withJson($this->presenter->validationError('Name is required.', ['name' => 'Name is required.']));
        }

        if (!is_array($variants) || count($variants) < 2) {
            return $response->withStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
                ->withJson($this->presenter->validationError('At least 2 variants are required.'));
        }

        $record = new AbTestRecord([
            'name' => $name,
            'variants' => json_encode(array_values($variants)),
            'status' => 'active',
        ]);

        $this->testsTable->insert($record);

        return $response->withStatus(Response::HTTP_CREATED)
            ->withJson($this->presenter->success('Test created.'));
    }
}
