<?php

declare(strict_types=1);

namespace App\Auth\Login;

use Handlr\Auth\AuthorizationService;
use Handlr\Handlers\ValidatedInputFactory;
use Handlr\Api\Presenter;
use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Database\DatabaseException;
use Handlr\Pipes\Pipe;

class PostLoginAttempt implements Pipe
{
    public function __construct(
        private readonly LoginHandler $handler,
        private readonly ValidatedInputFactory $factory,
        private readonly Presenter $presenter,
        private readonly AuthorizationService $authService
    ) {}

    /**
     * @throws DatabaseException
     */
    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        /** @var LoginInput $input */
        /** @see LoginInput::validate() */
        [$input, $errors] = $this->factory->makeValidatedInput(
            $request,
            LoginInput::class,
            'validate'
        );

        if ($errors) {
            return $response->withStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
                ->withJson($this->presenter->validationError('Please check your input.', $errors));
        }

        $result = $this->handler->handle($input);
        if (!$result?->success) {
            return $response->withStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
                ->withJson($this->presenter->invariantError($result->errors[0] ?? null));
        }

        $redirectUrl = $this->determineRedirectUrl();

        return $response->withStatus(Response::HTTP_OK)
            ->withJson(
                $this->presenter
                    ->withMeta(['redirect' => $redirectUrl])
                    ->success()
            );
    }

    private function determineRedirectUrl(): string
    {
        return '/';
    }
}
