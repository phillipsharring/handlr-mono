<?php

declare(strict_types=1);

namespace Handlr\Resolution;

use Handlr\Auth\AuthContext;
use Handlr\Core\Container\ContainerInterface;
use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Database\Table;
use Handlr\Pipes\Pipe;
use Handlr\Policies\Policy;
use RuntimeException;

/**
 * Resolve → consult → bind, driven by a route's {@see Resolves} metadata.
 *
 * 1. Resolve the record via its registered Table (missing → 404 via RecordNotFound).
 * 2. If the spec carries an action, consult the record's Policy and short-circuit
 *    on denial (403 via PolicyDenied).
 * 3. Bind the record into the request scope so the handler receives it by type hint.
 *
 * The Router injects this pipe just before the route handler when a route has a
 * Resolves spec — after auth pipes have populated the request context.
 */
final class ResolvePipe implements Pipe
{
    public function __construct(
        private readonly Resolves $spec,
        private readonly ContainerInterface $scope,
        private readonly ResolutionRegistry $registry,
        private readonly Resolver $resolver,
        private readonly AuthContext $actor,
    ) {}

    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        $binding = $this->registry->lookup($this->spec->record);

        /** @var Table $table */
        $table = $this->scope->get($binding['table']);

        $id = $args[$this->spec->param]
            ?? throw new RecordNotFound("Missing route parameter '{$this->spec->param}'.");

        $record = $this->resolver->resolve($table, $id);

        if ($this->spec->action !== null) {
            $policyClass = $binding['policy']
                ?? throw new RuntimeException(
                    "Record {$this->spec->record} declares a policy action but no policy is registered."
                );

            /** @var Policy $policy */
            $policy = $this->scope->get($policyClass);
            $policy->consult($this->actor, $this->spec->action, $record)->orDeny();
        }

        // Bind for downstream handlers to receive by type hint.
        $this->scope->singleton($this->spec->record, $record);

        return $next($request, $response, $args);
    }
}
