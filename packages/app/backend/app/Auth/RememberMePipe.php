<?php

declare(strict_types=1);

namespace App\Auth;

use App\Users\Data\UsersTable;
use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Pipes\Pipe;
use Handlr\Session\SessionInterface;

class RememberMePipe implements Pipe
{
    public function __construct(
        private readonly UsersTable $users,
        private readonly SessionInterface $session,
    ) {}

    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        // Skip if already authenticated via session
        if ($this->session->get('user_id')) {
            return $next($request, $response, $args);
        }

        $token = $_COOKIE['remember_token'] ?? null;

        if (!$token) {
            return $next($request, $response, $args);
        }

        $user = $this->users->findFirst([], ['remember_token' => $token]);

        if (!$user) {
            // Invalid token — clear the stale cookie
            setcookie('remember_token', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => ($_SERVER['HTTPS'] ?? '') === 'on',
            ]);
            return $next($request, $response, $args);
        }

        // Valid token — create a session
        $this->session->regenerate();
        $this->session->set('user_id', $user->id);

        return $next($request, $response, $args);
    }
}
