<?php

declare(strict_types=1);

namespace Handlr\Ab\Pipes;

use Handlr\Ab\AbService;
use Handlr\Ab\Domain\AbEventCapturedEvent;
use Handlr\Api\Presenter;
use Handlr\Core\EventManager;
use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Pipes\Pipe;

class CaptureAbEvent implements Pipe
{
    public function __construct(
        private AbService $abService,
        private EventManager $eventManager,
        private Presenter $presenter,
    ) {}

    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        $body = $request->getParsedBody() ?? [];
        $event = $body['event'] ?? '';
        $assignments = $body['assignments'] ?? [];

        if (!$event || !is_array($assignments) || empty($assignments)) {
            return $response->withStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
                ->withJson($this->presenter->validationError('Event and assignments are required.'));
        }

        $sessionId = session_id();

        $this->abService->recordEvent($sessionId, $event, $assignments);

        $this->eventManager->dispatch('ab.event.captured', new AbEventCapturedEvent([
            'session_id' => $sessionId,
            'event' => $event,
            'assignments' => $assignments,
        ]));

        return $response->withStatus(Response::HTTP_OK)
            ->withJson($this->presenter->success('Event captured.'));
    }
}
