<?php

declare(strict_types=1);

namespace Handlr\Mail;

use Psr\Log\LoggerInterface;

/**
 * Mail transport that logs messages instead of sending them.
 *
 * Writes the full email (envelope + body) to the application log.
 * Use this in development and testing to verify emails are composed
 * correctly without actually sending anything.
 *
 * ## Configuration
 *
 * ```php
 * // config/app.php
 * 'mail' => [
 *     'transport' => 'log',
 * ]
 * ```
 *
 * ## Log output
 *
 * ```
 * [2026-03-26 10:30:00] INFO: [Mail] To: user@example.com | Subject: Welcome | HTML: 234 chars
 * ```
 */
class LogTransport implements MailTransportInterface
{
    public function __construct(private LoggerInterface $logger) {}

    public function send(MailMessage $message): void
    {
        $this->logger->info('[Mail] To: {to} | Subject: {subject} | HTML: {length} chars', [
            'to' => $message->getTo(),
            'subject' => $message->getSubject(),
            'length' => strlen($message->getHtml()),
        ]);

        $this->logger->debug('[Mail] Body: {html}', [
            'html' => $message->getHtml(),
        ]);
    }
}
