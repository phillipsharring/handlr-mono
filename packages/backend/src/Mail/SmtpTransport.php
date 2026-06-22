<?php

declare(strict_types=1);

namespace Handlr\Mail;

use Psr\Log\LoggerInterface;

/**
 * Mail transport that sends via SMTP.
 *
 * Simple SMTP client for local development tools like MailHog, Mailpit,
 * or any standard SMTP server that doesn't require authentication.
 *
 * For authenticated SMTP (Gmail, SendGrid, etc.), use the SES transport
 * or extend this class with AUTH support.
 *
 * ## Configuration (MailHog)
 *
 * ```php
 * // config.php
 * 'mail' => [
 *     'transport' => 'smtp',
 *     'host' => '127.0.0.1',
 *     'port' => 1025,
 *     'from_address' => 'hello@reuselists.com',
 *     'from_name' => 'Reuse Lists',
 * ]
 * ```
 *
 * ## MailHog
 *
 * MailHog catches all outbound email on port 1025 and displays it
 * in a web UI at http://localhost:8025. No emails leave your machine.
 */
class SmtpTransport implements MailTransportInterface
{
    public function __construct(
        private string $host,
        private int $port,
        private LoggerInterface $logger,
    ) {}

    public function send(MailMessage $message): void
    {
        $socket = @fsockopen($this->host, $this->port, $errno, $errstr, 5);

        if (!$socket) {
            $this->logger->error('[Mail] SMTP connection failed: {error} ({host}:{port})', [
                'error' => $errstr,
                'host' => $this->host,
                'port' => $this->port,
            ]);
            throw new \RuntimeException("SMTP connection failed: $errstr ($this->host:$this->port)");
        }

        try {
            $this->read($socket);
            $this->command($socket, "EHLO localhost");
            $this->command($socket, "MAIL FROM:<{$message->getFrom()}>");
            $this->command($socket, "RCPT TO:<{$message->getTo()}>");

            if ($message->getCc()) {
                $this->command($socket, "RCPT TO:<{$message->getCc()}>");
            }

            $this->command($socket, "DATA");

            $headers = $this->buildHeaders($message);
            $body = $headers . "\r\n" . $message->getHtml();

            fwrite($socket, $body . "\r\n.\r\n");
            $this->read($socket);

            $this->command($socket, "QUIT");
        } catch (\Throwable $e) {
            $this->logger->error('[Mail] SMTP send failed: {error}', [
                'error' => $e->getMessage(),
                'to' => $message->getTo(),
            ]);
            throw $e;
        } finally {
            fclose($socket);
        }
    }

    private function buildHeaders(MailMessage $message): string
    {
        $from = $message->getFromName()
            ? sprintf('"%s" <%s>', $message->getFromName(), $message->getFrom())
            : $message->getFrom();

        $headers = "From: $from\r\n";
        $headers .= "To: {$message->getTo()}\r\n";

        if ($message->getCc()) {
            $headers .= "Cc: {$message->getCc()}\r\n";
        }

        if ($message->getReplyTo()) {
            $headers .= "Reply-To: {$message->getReplyTo()}\r\n";
        }

        $headers .= "Subject: {$message->getSubject()}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: 7bit\r\n";
        $headers .= "Date: " . date('r') . "\r\n";

        return $headers;
    }

    private function command($socket, string $command): void
    {
        fwrite($socket, $command . "\r\n");
        $this->read($socket);
    }

    private function read($socket): string
    {
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $response;
    }
}
