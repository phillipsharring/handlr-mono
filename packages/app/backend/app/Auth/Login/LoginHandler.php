<?php

declare(strict_types=1);

namespace App\Auth\Login;

use App\Auth\Login\Domain\Events\UserLoggedInEvent;
use App\Users\Data\UsersTable;
use App\Users\Domain\UserRecord;
use Handlr\Core\EventManager;
use Handlr\Database\DatabaseException;
use Handlr\Handlers\Handler;
use Handlr\Handlers\HandlerInput;
use Handlr\Handlers\HandlerResult;
use Handlr\Session\SessionInterface;

class LoginHandler implements Handler
{
    public function __construct(
        private UsersTable $users,
        private SessionInterface $session,
        private EventManager $eventManager,
        private HandlerResult $result
    ) {}

    /**
     * @throws DatabaseException
     */
    public function handle(LoginInput|HandlerInput|array $input): ?HandlerResult
    {
        $email = $input->email;
        $password = $input->password;

        /** @var UserRecord|null $user */
        $user = $this->users->findFirst([], ['email' => $email]);

        if ($user === null) {
            return $this->result->fail(['Invalid email or password.']);
        }

        if (!password_verify($password, $user->password)) {
            return $this->result->fail(['Invalid email or password.']);
        }

        if ($user->blocked_at !== null) {
            return $this->result->fail(['This account has been blocked.']);
        }

        $this->session->regenerate();
        $this->session->set('user_id', $user->id);

        // Remember me — store token and set persistent cookie
        if ($input->remember_me) {
            $token = bin2hex(random_bytes(32));
            $user->remember_token = $token;
            $this->users->update($user);

            setcookie('remember_token', $token, [
                'expires' => time() + (30 * 24 * 60 * 60), // 30 days
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => ($_SERVER['HTTPS'] ?? '') === 'on',
            ]);
        }

        // Dispatch login event for Daily Tribute and other on_login effects
        $this->eventManager->dispatch('user.logged_in', new UserLoggedInEvent([
            'user_id' => $user->id,
        ]));

        return $this->result->ok($user);
    }
}
