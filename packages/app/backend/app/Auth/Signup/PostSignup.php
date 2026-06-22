<?php

declare(strict_types=1);

namespace App\Auth\Signup;

use Handlr\Handlers\ValidatedInputFactory;
use Handlr\Api\Presenter;
use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Pipes\Pipe;

class PostSignup implements Pipe
{
    public function __construct(
        private SignupHandler $handler,
        private ValidatedInputFactory $factory,
        private Presenter $presenter,
    ) {}

    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        /**
         * @var SignupInput $input
         * @see SignupInput::validate()
         */
        [$input, $errors] = $this->factory->makeValidatedInput(
            $request,
            SignupInput::class,
            'validate'
        );
        if ($errors) {
            return $response->withStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
                ->withJson($this->presenter->validationError('Please check your input.', $errors));
        }

        $result = $this->handler->handle($input);
        if (!$result?->success) {
            $message = $result?->errors[0] ?? 'Could not create account.';
            return $response->withStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
                ->withJson($this->presenter->invariantError($message));
        }

        return $response->withStatus(Response::HTTP_OK)
            ->withJson(
                $this->presenter
                    ->withMeta(['redirect' => '/'])
                    ->success('Account created successfully.')
            );
    }
}
