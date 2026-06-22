<?php

declare(strict_types=1);

namespace App\Auth;

use App\Users\Data\UsersTable;
use Handlr\Auth\AuthContext;
use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Pipes\Pipe;
use Handlr\Session\SessionInterface;

class CheckBlockedPipe implements Pipe
{
    public function __construct(
        private readonly AuthContext $authContext,
        private readonly UsersTable $users,
        private readonly SessionInterface $session,
    ) {}

    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        if (!$this->authContext->isAuthenticated()) {
            return $next($request, $response, $args);
        }

        $user = $this->users->findById($this->authContext->getUserId());
        if ($user && $user->blocked_at !== null) {
            $this->session->destroy();
            return $response->withStatus(Response::HTTP_UNAUTHORIZED)
                ->withJson(['status' => 'error', 'message' => 'This account has been blocked.']);
        }

        return $next($request, $response, $args);
    }
}
