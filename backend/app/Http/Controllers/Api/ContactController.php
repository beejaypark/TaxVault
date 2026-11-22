<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

final class ContactController
{
    public function __invoke(Request $request): Response
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:254'],
            'message' => ['required', 'string', 'max:3000'],
        ]);

        $recipient = config('mail.support_address');
        if (! $recipient && app()->isLocal() && config('mail.default') === 'smtp'
            && config('mail.mailers.smtp.host') === 'mailpit' && ! config('mail.mailers.smtp.url')) {
            $recipient = 'support@taxvault.test';
        }

        abort_unless(is_string($recipient) && filter_var($recipient, FILTER_VALIDATE_EMAIL), 503, 'Contact form is unavailable.');

        $body = "Name: {$input['name']}\nEmail: {$input['email']}\n\n{$input['message']}";
        try {
            Mail::raw($body, function (Message $message) use ($recipient, $input): void {
                $message->to($recipient)->replyTo($input['email'])->subject('TaxVault website enquiry');
            });
        } catch (TransportExceptionInterface) {
            Log::warning('TaxVault support enquiry delivery failed.');
            abort(503, 'Unable to deliver your message. Please try again.');
        }

        return response()->noContent();
    }
}
