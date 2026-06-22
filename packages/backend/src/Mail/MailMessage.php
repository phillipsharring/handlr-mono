<?php

declare(strict_types=1);

namespace Handlr\Mail;

/**
 * Value object representing an email message.
 *
 * Built via fluent methods from the Mailer factory. Carries the envelope
 * (to, from, subject) and either a view template path or raw HTML body.
 *
 * ## Usage (via Mailer)
 *
 * ```php
 * $message = $this->mailer->message()
 *     ->to('user@example.com')
 *     ->subject('Welcome')
 *     ->view('emails/welcome', ['name' => 'Phil']);
 *
 * $this->mailer->send($message);
 * ```
 *
 * ## With raw HTML
 *
 * ```php
 * $message = $this->mailer->message()
 *     ->to('admin@example.com')
 *     ->subject('Alert')
 *     ->html('<p>Something happened.</p>');
 * ```
 *
 * ## Multiple recipients
 *
 * ```php
 * $message = $this->mailer->message()
 *     ->to('one@example.com')
 *     ->cc('two@example.com')
 *     ->subject('Update')
 *     ->view('emails/update', $data);
 * ```
 */
class MailMessage
{
    private string $toAddress = '';
    private string $toName = '';
    private string $fromAddress = '';
    private string $fromName = '';
    private string $ccAddress = '';
    private string $replyToAddress = '';
    private string $subjectLine = '';
    private string $viewPath = '';
    private array $viewData = [];
    private string $htmlBody = '';
    private string $textBody = '';

    /**
     * Set the recipient.
     *
     * @param string $address Email address
     * @param string $name    Optional display name
     */
    public function to(string $address, string $name = ''): static
    {
        $this->toAddress = $address;
        $this->toName = $name;
        return $this;
    }

    /**
     * Set the sender (overrides the default from config).
     *
     * @param string $address Email address
     * @param string $name    Optional display name
     */
    public function from(string $address, string $name = ''): static
    {
        $this->fromAddress = $address;
        $this->fromName = $name;
        return $this;
    }

    /**
     * Set a CC recipient.
     *
     * @param string $address Email address
     */
    public function cc(string $address): static
    {
        $this->ccAddress = $address;
        return $this;
    }

    /**
     * Set the reply-to address.
     *
     * @param string $address Email address
     */
    public function replyTo(string $address): static
    {
        $this->replyToAddress = $address;
        return $this;
    }

    /**
     * Set the subject line.
     */
    public function subject(string $subject): static
    {
        $this->subjectLine = $subject;
        return $this;
    }

    /**
     * Set the body from a view template.
     *
     * The view path is relative to `resources/views/` (no .php extension).
     * Data is extracted into the template scope.
     *
     * @param string $path View path (e.g., 'emails/welcome')
     * @param array  $data Variables to pass to the template
     */
    public function view(string $path, array $data = []): static
    {
        $this->viewPath = $path;
        $this->viewData = $data;
        return $this;
    }

    /**
     * Set the body from raw HTML.
     */
    public function html(string $html): static
    {
        $this->htmlBody = $html;
        return $this;
    }

    /**
     * Set an optional plain-text body (sent as the text/plain part).
     */
    public function text(string $text): static
    {
        $this->textBody = $text;
        return $this;
    }

    // ── Getters (used by Mailer and transports) ──

    public function getTo(): string { return $this->toAddress; }
    public function getToName(): string { return $this->toName; }
    public function getFrom(): string { return $this->fromAddress; }
    public function getFromName(): string { return $this->fromName; }
    public function getCc(): string { return $this->ccAddress; }
    public function getReplyTo(): string { return $this->replyToAddress; }
    public function getSubject(): string { return $this->subjectLine; }
    public function getViewPath(): string { return $this->viewPath; }
    public function getViewData(): array { return $this->viewData; }
    public function getHtml(): string { return $this->htmlBody; }
    public function getText(): string { return $this->textBody; }
    public function hasView(): bool { return $this->viewPath !== ''; }
}
