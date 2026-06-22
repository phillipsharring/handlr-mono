<?php

declare(strict_types=1);

namespace App\Auth;

use App\Users\Data\UsersTable;
use App\Users\Domain\UserRecord;
use Handlr\Api\Presenter;
use Handlr\Auth\AuthContext;
use Handlr\Auth\AuthorizationService;
use Handlr\Auth\AuthorizedUser;
use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Pipes\Pipe;

class GetAuthStatus implements Pipe
{
    public function __construct(
        private readonly AuthContext $authContext,
        private readonly AuthorizationService $authService,
        private readonly UsersTable $users,
        private readonly Presenter $presenter
    ) {}

    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        if (!$this->authContext->isAuthenticated()) {
            return $response->withStatus(Response::HTTP_OK)
                ->withJson(
                    $this->presenter
                        ->withSingleData(['authenticated' => false])
                        ->success()
                );
        }

        $subject = $this->authService->subject();
        $permissions = $subject instanceof AuthorizedUser ? $subject->getPermissions() : [];

        /** @var UserRecord|null $user */
        $user = $this->users->findById($subject->id());

        return $response->withStatus(Response::HTTP_OK)
            ->withJson(
                $this->presenter
                    ->withSingleData([
                        'authenticated' => true,
                        'user' => [
                            'id' => $subject->id(),
                            'username' => $user?->username,
                        ],
                    ])
                    ->withMeta([
                        'permissions' => $permissions,
                    ])
                    ->success()
            );
    }
}
