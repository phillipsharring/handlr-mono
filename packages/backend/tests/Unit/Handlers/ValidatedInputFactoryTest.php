<?php

declare(strict_types=1);

use Handlr\Handlers\ValidatedInputFactory;
use Handlr\Handlers\HandlerInput;
use Handlr\Core\Request;
use Handlr\Validation\Validator;
use Handlr\Validation\Rules\RuleValidatorFactory;
use Handlr\Validation\Sanitizers\SanitizerFactory;

/**
 * Captures the merged data it was constructed with and exposes a
 * named validation method that returns canned errors.
 */
class SpyInput implements HandlerInput
{
    public array $seen;

    public function __construct(array $body = [], ?Validator $validator = null)
    {
        $this->seen = $body;
    }

    public function validateIt(): array
    {
        return ['field' => 'boom'];
    }
}

function makeFactory(): ValidatedInputFactory
{
    return new ValidatedInputFactory(new Validator(new RuleValidatorFactory(), new SanitizerFactory()));
}

/** createMock is protected, so the Request stub must be built inside the test closure. */
function stubRequest(Request $req, array $route, array $body): Request
{
    $req->method('getRouteParams')->willReturn($route);
    $req->method('getParsedBody')->willReturn($body);
    return $req;
}

it('merges route params and body into the input', function () {
    $req = stubRequest($this->createMock(Request::class), ['id' => 'r1'], ['name' => 'bob']);

    [$input] = makeFactory()->makeValidatedInput($req, SpyInput::class);

    expect($input)->toBeInstanceOf(SpyInput::class);
    expect($input->seen)->toBe(['id' => 'r1', 'name' => 'bob']);
});

it('additionalData wins over body (server-set values cannot be overridden)', function () {
    $req = stubRequest($this->createMock(Request::class), [], ['user_id' => 'attacker', 'name' => 'bob']);

    [$input] = makeFactory()->makeValidatedInput(
        $req,
        SpyInput::class,
        null,
        ['user_id' => 'trusted'],
    );

    expect($input->seen['user_id'])->toBe('trusted');
    expect($input->seen['name'])->toBe('bob');
});

it('runs the named validation method and returns its errors', function () {
    $req = stubRequest($this->createMock(Request::class), [], []);

    [$input, $errors] = makeFactory()->makeValidatedInput($req, SpyInput::class, 'validateIt');

    expect($errors)->toBe(['field' => 'boom']);
});

it('returns empty errors when no validation method is given', function () {
    $req = stubRequest($this->createMock(Request::class), [], []);

    [, $errors] = makeFactory()->makeValidatedInput($req, SpyInput::class);

    expect($errors)->toBe([]);
});
