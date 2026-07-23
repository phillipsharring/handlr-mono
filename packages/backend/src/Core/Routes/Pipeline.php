<?php

declare(strict_types=1);

namespace Handlr\Core\Routes;

use Handlr\Core\Response;
use Handlr\Pipes\Pipe;

/**
 * Middleware pipeline that executes pipes in sequence (onion pattern).
 *
 * Pipes are executed in the order they are added. Each pipe can:
 * - Modify the request/response before passing to the next pipe
 * - Short-circuit by returning a response without calling $next
 * - Modify the response after the inner pipes complete
 *
 * Execution flows like an onion: first pipe's "before" logic runs first,
 * but its "after" logic runs last.
 *
 * @example Building a pipeline:
 *     $pipeline = new Pipeline();
 *     $pipeline
 *         ->lay(new AuthPipe())      // Runs 1st on way in, last on way out
 *         ->lay(new LogPipe())       // Runs 2nd on way in, 2nd-to-last out
 *         ->lay(new ValidationPipe()); // Runs 3rd on way in, first on way out
 *
 * @example Execution order for 3 pipes (A, B, C):
 *     A before → B before → C before → [handler] → C after → B after → A after
 */
class Pipeline
{
    /**
     * Pipe resolvers, in execution order. Each entry is a thunk returning the
     * Pipe. Eager pipes wrap an already-built instance; deferred pipes resolve
     * lazily when (and only if) the chain reaches them.
     *
     * @var array<callable(): Pipe>
     */
    private array $pipes = [];

    /**
     * Add an already-built pipe to the pipeline.
     *
     * Pipes are executed in the order they are added (first in, first to run).
     * Returns $this for method chaining.
     *
     * @param Pipe $pipe The pipe (middleware) to add
     * @return self Fluent interface
     *
     * @example Chain multiple pipes:
     *     $pipeline
     *         ->lay(new AuthPipe())
     *         ->lay(new RateLimitPipe())
     *         ->lay(new CachePipe());
     */
    public function lay(Pipe $pipe): self
    {
        $this->pipes[] = static fn(): Pipe => $pipe;
        return $this;
    }

    /**
     * Add a lazily-resolved pipe.
     *
     * The resolver is invoked at most once, only when the chain actually reaches
     * this link. A pipe upstream that short-circuits (never calls $next) means
     * this resolver never runs — so the pipe is never constructed. This is how
     * route handlers stay unbuilt until auth/policy pipes have cleared the way.
     *
     * @param callable(): Pipe $resolver Builds the pipe on demand.
     * @return self Fluent interface
     */
    public function defer(callable $resolver): self
    {
        $this->pipes[] = $resolver;
        return $this;
    }

    /**
     * Execute all pipes in the pipeline.
     *
     * Builds a nested closure chain and executes it. If no pipes are added,
     * simply returns the response unchanged.
     *
     * @param mixed $request The HTTP request object
     * @param Response $response The initial response object
     * @param array<string, mixed> $args Route arguments and other context
     * @return Response The final response after all pipes have executed
     *
     * @example
     *     $response = $pipeline->run($request, new Response(), ['id' => '123']);
     */
    public function run($request, $response, $args): Response
    {
        $next = static fn($req, $res, $args) => $res;

        foreach (array_reverse($this->pipes) as $resolve) {
            $next = static fn($req, $res, $args) => $resolve()->handle($req, $res, $args, $next);
        }

        return $next($request, $response, $args);
    }
}
