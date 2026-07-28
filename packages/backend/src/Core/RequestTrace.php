<?php

declare(strict_types=1);

namespace Handlr\Core;

use Ramsey\Uuid\Uuid;

/**
 * Per-request identity and timing — the correlation record for one request.
 *
 * Named "trace" rather than "context" on purpose: it is the request's
 * observability handle (a correlation id + timing + actor), distinct from the
 * "context" array you pass to a log call.
 *
 * Bound as a singleton on the root container and reset at the top of each
 * {@see \Handlr\Core\Routes\Router::dispatch()}. It lives on the root container
 * (not the per-request scope) because global pipes such as
 * {@see \Handlr\Pipes\LogPipe} are built from the root and would not see a
 * scope-only binding. Root pipes inject it once and read its live fields; scoped
 * handlers resolve it upward through the request scope. It is intentionally
 * mutable: `begin()` re-initializes it per request, so a single instance is
 * correct even under long-running workers (each dispatch resets it).
 */
final class RequestTrace
{
    private string $id;
    private string $method = '';
    private string $path = '';
    private ?string $actor = null;
    private float $startedAt;

    public function __construct()
    {
        $this->id = self::newId();
        $this->startedAt = microtime(true);
    }

    /**
     * (Re)initialize for a new request: fresh id, method, path, start time, and a
     * cleared actor. Called at the top of Router::dispatch().
     */
    public function begin(string $method, string $path): void
    {
        $this->id = self::newId();
        $this->method = $method;
        $this->path = $path;
        $this->actor = null;
        $this->startedAt = microtime(true);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /** The authenticated actor (e.g. user id), once an auth pipe has set it. */
    public function getActor(): ?string
    {
        return $this->actor;
    }

    public function setActor(?string $actor): void
    {
        $this->actor = $actor;
    }

    /** Milliseconds elapsed since this request started. */
    public function elapsedMs(): float
    {
        return round((microtime(true) - $this->startedAt) * 1000, 2);
    }

    private static function newId(): string
    {
        return Uuid::uuid7()->toString();
    }
}
