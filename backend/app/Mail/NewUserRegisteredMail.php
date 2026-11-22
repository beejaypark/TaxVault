<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class NewUserRegisteredMail extends Mailable
{
    public function __construct(
        public readonly string $displayName,
        public readonly string $email,
        public readonly string $provider,
        public readonly string $registeredAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[TaxVault] New customer registration');
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>A new TaxVault customer has registered.</p>'
            .'<p><strong>Name:</strong> '.e($this->displayName).'<br>'
            .'<strong>Email:</strong> '.e($this->email).'<br>'
            .'<strong>Provider:</strong> '.e($this->provider).'<br>'
            .'<strong>Registered:</strong> '.e($this->registeredAt).'</p>');
    }
}
