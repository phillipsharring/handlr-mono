<?php

declare(strict_types=1);

namespace App\Auth\EmailVerification;

use Handlr\Api\Presenter;
use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Pipes\Pipe;

class GetVerifyEmail implements Pipe
{
    public function __construct(
        private readonly VerifyEmailHandler $handler,
        private readonly Presenter $presenter,
    ) {}

    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        $token = $request->getQueryParams()['token'] ?? '';

        if (!$token) {
            return $response->withStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
                ->withJson($this->presenter->invariantError('Missing verification token.'));
        }

        $input = new VerifyEmailInput(['token' => $token]);
        $result = $this->handler->handle($input);

        if (!$result?->success) {
            return $response->withStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
                ->withJson($this->presenter->invariantError($result->errors[0] ?? 'Unable to verify email.'));
        }

        return $response->withStatus(Response::HTTP_OK)
            ->withJson($this->presenter->success('Your email has been verified.'));
    }
}
