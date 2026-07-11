<?php

declare(strict_types=1);

use Handlr\Core\Routes\Pipeline;
use Handlr\Core\Response;
use Handlr\Pipes\Pipe;

/**
 * Records its entry/exit around $next to prove onion ordering.
 */
class TracePipe implements Pipe
{
    public function __construct(private string $tag, private array &$log) {}

    public function handle($request, $response, array $args, callable $next): Response
    {
        $this->log[] = "{$this->tag}:before";
        $response = $next($request, $response, $args);
        $this->log[] = "{$this->tag}:after";
        return $response;
    }
}

/**
 * Short-circuits: never calls $next.
 */
class ShortCircuitPipe implements Pipe
{
    public function __construct(private array &$log) {}

    public function handle($request, $response, array $args, callable $next): Response
    {
        $this->log[] = 'short:handled';
        return $response;
    }
}

it('returns the response unchanged when there are no pipes', function () {
    $response = new Response();
    $out = (new Pipeline())->run(null, $response, []);
    expect($out)->toBe($response);
});

it('executes pipes in onion order (first in = outermost)', function () {
    $log = [];
    $pipeline = (new Pipeline())
        ->lay(new TracePipe('A', $log))
        ->lay(new TracePipe('B', $log));

    $pipeline->run(null, new Response(), []);

    expect($log)->toBe([
        'A:before',
        'B:before',
        'B:after',
        'A:after',
    ]);
});

it('short-circuits: inner pipes never run', function () {
    $log = [];
    $pipeline = (new Pipeline())
        ->lay(new TracePipe('A', $log))
        ->lay(new ShortCircuitPipe($log))
        ->lay(new TracePipe('C', $log));

    $pipeline->run(null, new Response(), []);

    expect($log)->toBe([
        'A:before',
        'short:handled',
        'A:after',
    ]);
});

it('lay() is chainable', function () {
    $log = [];
    $pipeline = new Pipeline();
    expect($pipeline->lay(new ShortCircuitPipe($log)))->toBe($pipeline);
});

it('passes route args through to pipes', function () {
    $capturePipe = new class implements Pipe {
        public array $seen = [];
        public function handle($request, $response, array $args, callable $next): Response
        {
            $this->seen = $args;
            return $next($request, $response, $args);
        }
    };

    (new Pipeline())->lay($capturePipe)->run(null, new Response(), ['id' => '123']);

    expect($capturePipe->seen)->toBe(['id' => '123']);
});
