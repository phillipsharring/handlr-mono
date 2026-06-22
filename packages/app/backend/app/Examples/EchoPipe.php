<?php

declare(strict_types=1);

namespace App\Examples;

use Handlr\Api\Presenter;
use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Pipes\Pipe;
use JsonException;

class EchoPipe implements Pipe
{
    public function __construct(private Presenter $presenter) {}

    /**
     * @throws JsonException
     */
    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        // If the JSON body is invalid, Request::getParsedBody() will throw a RequestException,
        // which is handled by the global ErrorPipe.
        $payload = $request->getParsedBody();

        $name = is_string($payload['name'] ?? null) ? $payload['name'] : '';
        $animal = is_string($payload['animal'] ?? null) ? $payload['animal'] : '';

        return $response->withJson(
            $this->presenter
                ->withSingleData([
                    'name' => $name,
                    'animal' => $animal,
                    'json' => json_encode(
                        $payload,
                        JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                    ),
                ])
                ->success()
        );
    }
}
