<?php

declare(strict_types=1);

namespace App\Auth\Logout;

use App\Users\Data\UsersTable;
use Handlr\Handlers\Handler;
use Handlr\Handlers\HandlerInput;
use Handlr\Handlers\HandlerResult;
use Handlr\Session\SessionInterface;

class LogoutHandler implements Handler
{
    public function __construct(
        private readonly UsersTable $users,
        private readonly SessionInterface $session,
        private readonly HandlerResult $result
    ) {}

    public function handle(HandlerInput|array $input = []): HandlerResult
    {
        // Clear remember token
        $userId = $this->session->get('user_id');
        if ($userId) {
            $user = $this->users->findById($userId);
            if ($user && $user->remember_token !== null) {
                $user->remember_token = null;
                $this->users->update($user);
            }
        }

        setcookie('remember_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => ($_SERVER['HTTPS'] ?? '') === 'on',
        ]);

        $this->session->regenerate();
        $this->session->destroy();

        return $this->result->ok();
    }
}
