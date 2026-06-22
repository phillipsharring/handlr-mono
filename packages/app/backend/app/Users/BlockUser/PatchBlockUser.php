<?php

declare(strict_types=1);

namespace App\Users\BlockUser;

use App\Users\Data\UsersTable;
use App\Users\Inputs\UserInput;
use Handlr\Api\Presenter;
use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Handlers\ValidatedInputFactory;
use Handlr\Pipes\Pipe;

class PatchBlockUser implements Pipe
{
    public function __construct(
        private UsersTable $table,
        private ValidatedInputFactory $factory,
        private Presenter $presenter,
    ) {}

    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        /** @var UserInput $input */
        [$input, $errors] = $this->factory->makeValidatedInput(
            $request,
            UserInput::class,
            'validateId'
        );
        if ($errors) {
            return $response->withStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
                ->withJson($this->presenter->validationError('Invalid user.', $errors));
        }

        $user = $this->table->findById($input->id);
        if (!$user) {
            return $response->withStatus(Response::HTTP_NOT_FOUND)
                ->withJson($this->presenter->invariantError('User not found.'));
        }

        $body = $request->getParsedBody();
        $block = ($body['blocked'] ?? true);

        if ($block) {
            $user->blocked_at = date('Y-m-d H:i:s');
        } else {
            $user->blocked_at = null;
        }

        $this->table->update($user);

        $action = $block ? 'blocked' : 'unblocked';
        return $response->withStatus(Response::HTTP_OK)
            ->withJson($this->presenter->success("User {$action}."));
    }
}
