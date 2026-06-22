<?php

declare(strict_types=1);

namespace App\Profile;

use Handlr\Auth\AuthContext;
use App\Users\Data\UsersTable;
use App\Users\Domain\UserRecord;
use Handlr\Api\Presenter;
use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Pipes\Pipe;

class GetProfilePipe implements Pipe
{
    public function __construct(
        private AuthContext $authContext,
        private UsersTable $users,
        private Presenter $presenter,
    ) {}

    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        /** @var UserRecord|null $user */
        $user = $this->users->findById($this->authContext->getUserId());
        if (!$user) {
            return $response->withStatus(Response::HTTP_NOT_FOUND)
                ->withJson($this->presenter->invariantError('User not found.'));
        }

        return $response->withStatus(Response::HTTP_OK)
            ->withJson(
                $this->presenter
                    ->withSingleData([
                        'username' => $user->username,
                        'email' => $user->email,
                        'name' => $user->name,
                        'created_at' => $user->created_at?->format('M j, Y'),
                    ])
                    ->success()
            );
    }
}
