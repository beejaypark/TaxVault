<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/** A short account notification with an unsubscribe link and an optional calendar file. */
class AccountNotificationMail extends Mailable
{
    /**
     * @param  list<string>  $lines
     */
    public function __construct(
        public readonly string $mailSubject,
        public readonly string $greetingName,
        public readonly array $lines,
        public readonly string $link,
        public readonly string $linkLabel,
        public readonly ?string $unsubscribeUrl = null,
        public readonly ?string $calendarFile = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject);
    }

    public function headers(): Headers
    {
        return new Headers(
            text: $this->unsubscribeUrl ? ['List-Unsubscribe' => '<'.$this->unsubscribeUrl.'>'] : [],
        );
    }

    public function content(): Content
    {
        $paragraphs = collect($this->lines)->map(fn (string $line) => '<p>'.e($line).'</p>')->implode('');
        $footer = $this->unsubscribeUrl
            ? '<p style="color:#6b7280;font-size:12px">You receive this because email notifications are on in your TaxVault settings. '
                .'<a href="'.e($this->unsubscribeUrl).'">Turn off email notifications</a>.</p>'
            : '';

        return new Content(htmlString: '<p>Hello '.e($this->greetingName).',</p>'.$paragraphs
            .'<p><a href="'.e($this->link).'">'.e($this->linkLabel).'</a></p>'
            .'<p style="color:#6b7280;font-size:12px">TaxVault organises evidence for your accountant. It does not give tax advice, '
            .'and dates are reminders only: confirm them with the ATO or your tax agent.</p>'.$footer);
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        if ($this->calendarFile === null) {
            return [];
        }

        return [
            Attachment::fromData(fn () => $this->calendarFile, 'taxvault-reminder.ics')
                ->withMime('text/calendar'),
        ];
    }
}
