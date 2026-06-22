<?php

declare(strict_types=1);

namespace App\Examples;

use Handlr\Api\Presenter;
use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Pipes\Pipe;

class ToastPipe implements Pipe
{
    public function __construct(private Presenter $presenter) {}

    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        $status = $request->getQueryParams()['status'] ?? 'success';

        $messages = [
            'success' => 'Saved!',
            'error' => 'Something went wrong!',
            'warning' => 'Proceed with caution!',
        ];

        $message = $messages[$status] ?? $messages['success'];

        return match ($status) {
            'error' => $response->withJson($this->presenter->invariantError($message)),
            'warning' => $response->withJson($this->presenter->warning($message)),
            default => $response->withJson($this->presenter->success($message)),
        };
    }
}
