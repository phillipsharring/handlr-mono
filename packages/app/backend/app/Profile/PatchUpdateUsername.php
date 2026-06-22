<?php

declare(strict_types=1);

namespace App\Profile;

use Handlr\Auth\AuthContext;
use Handlr\Handlers\ValidatedInputFactory;
use Handlr\Api\Presenter;
use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Pipes\Pipe;

class PatchUpdateUsername implements Pipe
{
    public function __construct(
        private UpdateUsernameHandler $handler,
        private ValidatedInputFactory $factory,
        private AuthContext $authContext,
        private Presenter $presenter,
    ) {}

    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        /**
         * @var ProfileInput $input
         * @see ProfileInput::validateUsername()
         */
        [$input, $errors] = $this->factory->makeValidatedInput(
            $request,
            ProfileInput::class,
            'validateUsername',
            ['user_id' => $this->authContext->getUserId()]
        );
        if ($errors) {
            return $response->withStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
                ->withJson($this->presenter->validationError('Could not update username.', $errors));
        }

        $result = $this->handler->handle($input);
        if (!$result?->success) {
            $message = $result?->errors[0] ?? 'Could not update username.';
            return $response->withStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
                ->withJson($this->presenter->invariantError($message));
        }

        return $response->withStatus(Response::HTTP_OK)
            ->withJson($this->presenter->success('Username updated successfully.'));
    }
}
