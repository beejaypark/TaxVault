<?php

namespace App\Console\Commands;

use App\Mail\AccountNotificationMail;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class SendTrialEndingNotifications extends Command
{
    protected $signature = 'billing:send-trial-notifications';
    protected $description = 'Send trial ending notices 10, 5, 1 day before and on the last day.';

    public function handle(): int
    {
        $today = CarbonImmutable::now(config('notifications.timezone'))->startOfDay();
        $sent = 0;
        User::query()->where('status', 'active')->whereIn('stripe_subscription_status', ['trialing', 'paused'])
            ->whereNotNull('trial_ends_at')->orderBy('id')->each(function (User $user) use ($today, &$sent): void {
                $end = CarbonImmutable::parse($user->trial_ends_at)->setTimezone(config('notifications.timezone'))->startOfDay();
                $days = (int) $today->diffInDays($end, false);
                if (! in_array($days, [10, 5, 1, 0], true)) return;

                if (DB::table('email_notification_logs')->insertOrIgnore([
                    'user_id' => $user->getKey(), 'kind' => 'trial_ending',
                    'dedupe_key' => $end->toDateString().':'.$days, 'sent_at' => now(),
                ]) !== 1) return;

                $when = $end->format('j F Y');
                $message = $days === 0
                    ? "Your 30-day TaxVault free trial ends today ({$when}). Choose a monthly or yearly plan to keep uploading documents."
                    : "Your 30-day TaxVault free trial ends in {$days} ".($days === 1 ? 'day' : 'days')." ({$when}). Choose a monthly or yearly plan to keep uploading documents.";
                Mail::to($user->email)->send(new AccountNotificationMail(
                    mailSubject: $days === 0 ? 'TaxVault: your free trial ends today' : "TaxVault: {$days} days left in your free trial",
                    greetingName: $user->display_name ?? $user->email,
                    lines: [$message, 'After the trial ends, document uploads are paused until you subscribe.'],
                    link: rtrim((string) config('app.url'), '/').'/settings',
                    linkLabel: 'Choose a plan',
                    unsubscribeUrl: URL::signedRoute('email.unsubscribe', ['user' => $user->getKey()]),
                ));
                $sent++;
            });
        $this->info("Sent {$sent} trial notice(s).");
        return self::SUCCESS;
    }
}
