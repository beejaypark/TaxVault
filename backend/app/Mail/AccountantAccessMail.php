<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Either an invitation (no financial year) or a link to a specific pack. */
class AccountantAccessMail extends Mailable
{
    public function __construct(
        public readonly string $ownerName,
        public readonly string $accountantName,
        public readonly string $link,
        public readonly string $kind,
        public readonly ?string $yearLabel = null,
        public readonly ?string $note = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->kind === 'invitation'
                ? "{$this->ownerName} invited you to TaxVault"
                : "{$this->ownerName} shared an accountant pack".($this->yearLabel ? " for FY {$this->yearLabel}" : ''),
        );
    }

    public function content(): Content
    {
        $name = e($this->accountantName);
        $owner = e($this->ownerName);
        $link = e($this->link);
        $intro = $this->kind === 'invitation'
            ? "{$owner} has given you read-only access to their TaxVault records. Sign in with your TaxVault account to review them."
            : "{$owner} has shared an accountant pack".($this->yearLabel ? ' for FY '.e($this->yearLabel) : '').' with you. Sign in to review it.';
        $note = $this->note ? '<p>'.nl2br(e($this->note)).'</p>' : '';

        return new Content(htmlString: "<p>Hello {$name},</p><p>{$intro}</p>{$note}"
            ."<p><a href=\"{$link}\">Open TaxVault</a></p>"
            .'<p>Your sign-in details are provided separately by the sender. This link does not contain a password.</p>');
    }
}
