<?php

declare(strict_types=1);

namespace Handlr\Auth\Pipes;

use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Pipes\Pipe;
use Handlr\Session\SessionInterface;
use Handlr\Session\SessionTransport;

class StartSessionPipe implements Pipe
{
    public function __construct(
        private readonly SessionInterface $session,
        private readonly SessionTransport $transport,
    ) {}

    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        // In: the transport decides *how* to find the id (cookie, header,
        // bearer); the session only cares *what* the id is.
        $this->session->start($this->transport->extract($request));

        $response = $next($request, $response, $args);

        // Out: hand the (possibly freshly minted) id back in the same modality,
        // so a cookieless client can capture and re-send it. For the default
        // CookieTransport this is a pass-through — PHP already set the cookie.
        return $this->transport->emit($response, $this->session->id());
    }
}
