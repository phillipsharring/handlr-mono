<?php

declare(strict_types=1);

namespace App\Auth\Signup;

use App\Auth\Signup\Domain\Events\UserSignedUpEvent;
use App\Users\Data\UsersTable;
use App\Users\Domain\UserRecord;
use App\Users\ReservedUsernames;
use Handlr\Core\EventManager;
use Handlr\Handlers\Handler;
use Handlr\Handlers\HandlerInput;
use Handlr\Handlers\HandlerResult;
use Handlr\Session\SessionInterface;

class SignupHandler implements Handler
{
    public function __construct(
        private UsersTable $users,
        private ReservedUsernames $reservedUsernames,
        private SessionInterface $session,
        private EventManager $eventManager,
        private HandlerResult $result,
    ) {}

    public function handle(SignupInput|HandlerInput|array $input): ?HandlerResult
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $input->username)) {
            return $this->result->fail(['Username may only contain letters, numbers, and underscores.']);
        }

        if ($this->reservedUsernames->isReserved($input->username)) {
            return $this->result->fail(['This username is not available.']);
        }

        $existingUsername = $this->users->findFirst([], ['username' => $input->username]);
        if ($existingUsername) {
            return $this->result->fail(['This username is already taken.']);
        }

        $existingEmail = $this->users->findFirst([], ['email' => $input->email]);
        if ($existingEmail) {
            return $this->result->fail(['A user with this email already exists.']);
        }

        $user = new UserRecord([
            'name' => $input->username,
            'username' => $input->username,
            'email' => $input->email,
            'password' => password_hash($input->password, PASSWORD_BCRYPT, ['cost' => 12]),
        ]);

        $this->users->insert($user);

        $this->eventManager->dispatch('user.signed_up', new UserSignedUpEvent([
            'user_id' => $user->id,
        ]));

        // Auto-login
        $this->session->regenerate();
        $this->session->set('user_id', $user->id);

        return $this->result->ok($user);
    }
}
