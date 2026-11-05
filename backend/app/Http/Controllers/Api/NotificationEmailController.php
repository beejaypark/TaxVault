<?php

namespace App\Http\Controllers\Api;

use App\Domain\Notifications\Services\EmailNotificationSender;
use App\Http\Controllers\Controller;
use App\Mail\AccountNotificationMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotificationEmailController extends Controller
{
    public function sendTest(Request $request, EmailNotificationSender $sender): JsonResponse
    {
        /** @var User|null $user */
        $user = Auth::guard('web')->user();

        if (! $user || $user->isAccountant()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            Mail::to($user->email)->send(new AccountNotificationMail(
                mailSubject: 'TaxVault test email',
                greetingName: $user->display_name ?? $user->email,
                lines: ['This is a test email. If you can read it, TaxVault can send you notifications.'],
                link: rtrim((string) config('app.url'), '/').'/settings',
                linkLabel: 'Open settings',
                unsubscribeUrl: $sender->unsubscribeUrl($user),
            ));
        } catch (Throwable $exception) {
            Log::warning('Test email failed.', ['user_id' => $user->getKey(), 'error' => $exception->getMessage()]);

            return response()->json(['message' => 'The email could not be sent. Check the mail settings on the server.'], 502);
        }

        return response()->json(['data' => ['sent_to' => $user->email]]);
    }

    /** Reached from the signed link in every notification email; no sign-in needed. */
    public function unsubscribe(User $user): Response
    {
        $user->forceFill(['preferences' => array_merge($user->preferences ?? [], [
            'emailReviewDigest' => false,
            'emailDeadlineReminders' => false,
        ])])->save();

        return response(
            '<!doctype html><meta charset="utf-8"><title>Unsubscribed</title>'
            .'<body style="font-family:sans-serif;max-width:480px;margin:64px auto;padding:0 16px">'
            .'<h1>You are unsubscribed</h1><p>TaxVault will no longer send you email notifications. '
            .'You can turn them back on any time in Settings.</p></body>',
            200,
            ['Content-Type' => 'text/html; charset=utf-8'],
        );
    }
}
