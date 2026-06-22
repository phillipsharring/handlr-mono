<?php

declare(strict_types=1);

namespace Handlr\Mail;

use Aws\Ses\SesClient;
use Aws\Exception\AwsException;
use Psr\Log\LoggerInterface;

/**
 * Mail transport that sends via AWS Simple Email Service (SES).
 *
 * Requires the `aws/aws-sdk-php` package. Credentials are resolved by the
 * standard AWS SDK chain (env vars, IAM role, ~/.aws/credentials, etc.).
 *
 * ## Configuration
 *
 * ```php
 * // config/app.php
 * 'mail' => [
 *     'transport' => 'ses',
 *     'region' => 'us-east-1',
 *     'from_address' => 'hello@reuselists.com',
 *     'from_name' => 'Reuse Lists',
 * ]
 * ```
 *
 * ## Usage
 *
 * This transport is selected automatically when `mail.transport` is `'ses'`.
 * You don't interact with it directly — the Mailer handles routing.
 */
class SesTransport implements MailTransportInterface
{
    private SesClient $client;

    public function __construct(
        private string $region,
        private LoggerInterface $logger,
    ) {
        $this->client = new SesClient([
            'version' => 'latest',
            'region' => $this->region,
        ]);
    }

    public function send(MailMessage $message): void
    {
        $from = $message->getFromName()
            ? sprintf('"%s" <%s>', $message->getFromName(), $message->getFrom())
            : $message->getFrom();

        $args = [
            'Source' => $from,
            'Destination' => [
                'ToAddresses' => [$message->getTo()],
            ],
            'Message' => [
                'Subject' => [
                    'Charset' => 'UTF-8',
                    'Data' => $message->getSubject(),
                ],
                'Body' => [
                    'Html' => [
                        'Charset' => 'UTF-8',
                        'Data' => $message->getHtml(),
                    ],
                ],
            ],
        ];

        if ($message->getCc()) {
            $args['Destination']['CcAddresses'] = [$message->getCc()];
        }

        if ($message->getReplyTo()) {
            $args['ReplyToAddresses'] = [$message->getReplyTo()];
        }

        if ($message->getText()) {
            $args['Message']['Body']['Text'] = [
                'Charset' => 'UTF-8',
                'Data' => $message->getText(),
            ];
        }

        try {
            $this->client->sendEmail($args);
        } catch (AwsException $e) {
            $this->logger->error('[Mail] SES send failed: {error}', [
                'error' => $e->getAwsErrorMessage() ?? $e->getMessage(),
                'to' => $message->getTo(),
                'subject' => $message->getSubject(),
            ]);
            throw new \RuntimeException('Failed to send email via SES: ' . $e->getMessage(), 0, $e);
        }
    }
}
