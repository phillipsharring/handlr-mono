<?php

declare(strict_types=1);

namespace App\Auth\EmailVerification;

use App\Auth\Signup\Domain\Events\UserSignedUpEvent;
use Handlr\Api\Presenter;
use Handlr\Auth\AuthContext;
use Handlr\Core\EventManager;
use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Pipes\Pipe;

class PostResendVerification implements Pipe
{
    public function __construct(
        private readonly AuthContext $authContext,
        private readonly EventManager $eventManager,
        private readonly Presenter $presenter,
    ) {}

    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        $userId = $this->authContext->getUserId();

        // Reuse the same event — the listener checks if already verified
        $this->eventManager->dispatch('user.signed_up', new UserSignedUpEvent([
            'user_id' => $userId,
        ]));

        return $response->withStatus(Response::HTTP_OK)
            ->withJson($this->presenter->success('Verification email sent.'));
    }
}
