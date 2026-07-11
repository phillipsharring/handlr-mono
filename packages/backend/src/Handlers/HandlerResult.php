<?php

declare(strict_types=1);

namespace Handlr\Handlers;

/**
 * Value object representing the result of a Handler operation.
 *
 * Provides a consistent structure for handler responses with success/failure
 * status, data payload, error messages, and optional metadata.
 *
 * `ok()` and `fail()` are instance methods — the framework holds no static
 * state. Inject a HandlerResult into your handler (`private HandlerResult
 * $result`) and call `$this->result->ok(...)`. The examples below use an
 * injected `$result` for brevity.
 *
 * ## Creating success results
 *
 * ```php
 * // Simple success
 * return $result->ok();
 *
 * // Success with data
 * return $result->ok(['user' => $user->toArray()]);
 *
 * // Success with data and metadata
 * return $result->ok(
 *     ['users' => $users],
 *     ['total' => 100, 'page' => 1]
 * );
 * ```
 *
 * ## Creating failure results
 *
 * ```php
 * // Simple failure with error messages
 * return $result->fail(['User not found']);
 *
 * // Failure with field-specific errors
 * return $result->fail([
 *     'email' => 'Email is already taken',
 *     'username' => 'Username must be alphanumeric',
 * ]);
 *
 * // Failure with metadata (e.g., error codes)
 * return $result->fail(
 *     ['Payment declined'],
 *     ['code' => 'CARD_DECLINED', 'retry' => true]
 * );
 * ```
 *
 * ## Checking results
 *
 * ```php
 * $result = $handler->handle($input);
 *
 * if ($result->success) {
 *     $user = $result->data['user'];
 *     // ...
 * } else {
 *     foreach ($result->errors as $field => $message) {
 *         echo "$field: $message\n";
 *     }
 * }
 * ```
 *
 * ## Converting to Response
 *
 * ```php
 * // In a Pipe
 * $result = $handler->handle($input);
 * return $result->toResponse();  // Returns JSON Response with appropriate status
 * ```
 *
 * ## Accessing metadata
 *
 * ```php
 * $result = $this->result->ok($data, ['cached' => true, 'ttl' => 3600]);
 *
 * if ($result->meta['cached'] ?? false) {
 *     // Result was from cache
 * }
 * ```
 */
class HandlerResult
{
    /**
     * @param bool|null $success Whether the operation succeeded (null = indeterminate)
     * @param mixed     $data    Payload data for successful operations
     * @param array     $errors  Error messages (may be keyed by field name)
     * @param array     $meta    Additional metadata (pagination, debug info, etc.)
     */
    public function __construct(
        public readonly ?bool $success = null,
        public readonly mixed $data = null,
        public readonly array $errors = [],
        public readonly array $meta = [],
    ) {}

    /**
     * Create a successful result.
     *
     * ```php
     * return $this->result->ok(['user' => $user]);
     * return $this->result->ok($data, ['page' => 1, 'total' => 50]);
     * ```
     *
     * @param mixed $data Payload data
     * @param array $meta Optional metadata
     */
    public function ok(mixed $data = null, array $meta = []): HandlerResult
    {
        return new self(true, $data, [], $meta);
    }

    /**
     * Create a failure result.
     *
     * ```php
     * return $this->result->fail(['User not found']);
     * return $this->result->fail(['email' => 'Invalid email format']);
     * return $this->result->fail(['Payment failed'], ['code' => 'INSUFFICIENT_FUNDS']);
     * ```
     *
     * @param array $errors Error messages (may be keyed by field name)
     * @param array $meta   Optional metadata (error codes, debug info, etc.)
     */
    public function fail(array $errors, array $meta = []): HandlerResult
    {
        return new self(false, null, $errors, $meta);
    }
}
