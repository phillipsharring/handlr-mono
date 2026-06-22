<?php

declare(strict_types=1);

namespace Handlr\Mail;

use Handlr\Config\Config;
use Handlr\Views\View;
use Psr\Log\LoggerInterface;

/**
 * Mail service for composing and sending emails.
 *
 * Creates MailMessage instances pre-filled with default sender info from
 * config, renders view templates into HTML, and delegates delivery to
 * the configured transport (SES, log, etc.).
 *
 * ## Sending an email
 *
 * ```php
 * class SendWelcomeEmailListener implements Handler
 * {
 *     public function __construct(private Mailer $mailer) {}
 *
 *     public function handle(array|HandlerInput $input): ?HandlerResult
 *     {
 *         $this->mailer->send(
 *             $this->mailer->message()
 *                 ->to($input->email)
 *                 ->subject('Welcome to Reuse Lists')
 *                 ->view('emails/welcome', ['name' => $input->name])
 *         );
 *
 *         return null;
 *     }
 * }
 * ```
 *
 * ## Configuration
 *
 * ```php
 * // config/app.php
 * 'mail' => [
 *     'transport' => 'ses',        // 'ses' or 'log'
 *     'from_address' => 'hello@reuselists.com',
 *     'from_name' => 'Reuse Lists',
 *     'region' => 'us-east-1',     // SES region (only for 'ses' transport)
 * ]
 * ```
 *
 * ## Registration
 *
 * ```php
 * // In Kernel or app bootstrap
 * $container->singleton(Mailer::class);
 * ```
 */
class Mailer
{
    private MailTransportInterface $transport;
    private string $defaultFromAddress;
    private string $defaultFromName;

    public function __construct(Config $config, LoggerInterface $logger)
    {
        $this->defaultFromAddress = $config->get('mail.from_address', '');
        $this->defaultFromName = $config->get('mail.from_name', '');

        $transportType = $config->get('mail.transport', 'log');

        $this->transport = match ($transportType) {
            'ses' => new SesTransport(
                $config->get('mail.region', 'us-east-1'),
                $logger,
            ),
            'smtp' => new SmtpTransport(
                $config->get('mail.host', '127.0.0.1'),
                (int) $config->get('mail.port', 1025),
                $logger,
            ),
            default => new LogTransport($logger),
        };
    }

    /**
     * Create a new MailMessage pre-filled with the default sender.
     *
     * ```php
     * $message = $this->mailer->message()
     *     ->to('user@example.com')
     *     ->subject('Hello')
     *     ->view('emails/hello', ['name' => 'World']);
     * ```
     */
    public function message(): MailMessage
    {
        $message = new MailMessage();
        $message->from($this->defaultFromAddress, $this->defaultFromName);
        return $message;
    }

    /**
     * Render the message body (if using a view template) and send it.
     *
     * @param MailMessage $message The message to send
     *
     * @throws \RuntimeException If the transport fails
     */
    public function send(MailMessage $message): void
    {
        if ($message->hasView()) {
            $view = new View($message->getViewPath(), $message->getViewData());
            $message->html($view->render());
        }

        $this->transport->send($message);
    }
}
