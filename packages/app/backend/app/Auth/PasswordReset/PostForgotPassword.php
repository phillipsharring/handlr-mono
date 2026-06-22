<?php

declare(strict_types=1);

namespace App\Auth\PasswordReset;

use Handlr\Api\Presenter;
use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Handlers\ValidatedInputFactory;
use Handlr\Pipes\Pipe;

class PostForgotPassword implements Pipe
{
    public function __construct(
        private readonly ForgotPasswordHandler $handler,
        private readonly ValidatedInputFactory $factory,
        private readonly Presenter $presenter,
    ) {}

    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        [$input, $errors] = $this->factory->makeValidatedInput(
            $request,
            ForgotPasswordInput::class,
            'validate'
        );

        if ($errors) {
            return $response->withStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
                ->withJson($this->presenter->validationError('Please check your input.', $errors));
        }

        $this->handler->handle($input);

        // Always return success — don't reveal whether the email exists
        return $response->withStatus(Response::HTTP_OK)
            ->withJson($this->presenter->success('If that email is registered, a reset link has been sent.'));
    }
}
