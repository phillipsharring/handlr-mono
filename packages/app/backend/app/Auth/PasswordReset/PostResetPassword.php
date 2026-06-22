<?php

declare(strict_types=1);

namespace App\Auth\PasswordReset;

use Handlr\Api\Presenter;
use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Handlers\ValidatedInputFactory;
use Handlr\Pipes\Pipe;

class PostResetPassword implements Pipe
{
    public function __construct(
        private readonly ResetPasswordHandler $handler,
        private readonly ValidatedInputFactory $factory,
        private readonly Presenter $presenter,
    ) {}

    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        [$input, $errors] = $this->factory->makeValidatedInput(
            $request,
            ResetPasswordInput::class,
            'validate'
        );

        if ($errors) {
            return $response->withStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
                ->withJson($this->presenter->validationError('Please check your input.', $errors));
        }

        $result = $this->handler->handle($input);

        if (!$result?->success) {
            return $response->withStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
                ->withJson($this->presenter->invariantError($result->errors[0] ?? 'Unable to reset password.'));
        }

        return $response->withStatus(Response::HTTP_OK)
            ->withJson($this->presenter->success('Your password has been reset. You can now log in.'));
    }
}
