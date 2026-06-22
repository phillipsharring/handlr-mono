<?php

declare(strict_types=1);

namespace Handlr\Mail;

/**
 * Contract for mail transport implementations.
 *
 * Transports handle the actual delivery of a rendered MailMessage.
 * The message's HTML body will already be populated (from view or raw HTML)
 * by the time the transport receives it.
 *
 * ## Implementations
 *
 * - `SesTransport` — sends via AWS SES
 * - `LogTransport` — writes to log file (dev/testing)
 *
 * ## Custom transport
 *
 * ```php
 * class SmtpTransport implements MailTransportInterface
 * {
 *     public function send(MailMessage $message): void
 *     {
 *         // Send via SMTP...
 *     }
 * }
 * ```
 */
interface MailTransportInterface
{
    /**
     * Send a fully-rendered mail message.
     *
     * @param MailMessage $message The message to send (HTML body already populated)
     *
     * @throws \RuntimeException If sending fails
     */
    public function send(MailMessage $message): void;
}
