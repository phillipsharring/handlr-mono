<?php

declare(strict_types=1);

use Handlr\Mail\SesTransport;
use Psr\Log\NullLogger;

it('throws an actionable error when aws/aws-sdk-php is not installed', function () {
    // The framework only *suggests* aws/aws-sdk-php — it is not a hard dependency,
    // so Aws\Ses\SesClient is absent in this test environment. Selecting the SES
    // transport without the SDK must fail with a message that names the fix,
    // not a raw "Class not found".
    expect(fn () => new SesTransport('us-east-1', new NullLogger()))
        ->toThrow(RuntimeException::class, 'aws/aws-sdk-php');
})->skip(
    class_exists(\Aws\Ses\SesClient::class),
    'aws/aws-sdk-php is installed here; the missing-dependency guard is not exercisable',
);
