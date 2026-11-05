<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Documents\Models\Document;
use App\Domain\FinancialYears\Models\FinancialYear;
use App\Mail\AccountNotificationMail;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class EmailNotificationSender
{
    public const REMINDER_DAYS = [30, 7, 1];

    public const ATTENTION_STATUSES = ['review_required', 'processing_failed', 'awaiting_financial_year'];

    /** @return array{review: int, deadline: int} number of emails sent */
    public function run(?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::now(config('notifications.timezone'))->startOfDay();
        $sent = ['review' => 0, 'deadline' => 0];

        User::query()
            ->where('status', 'active')
            ->whereNull('deactivated_at')
            ->where(fn ($query) => $query->whereNull('role')->orWhere('role', '!=', 'accountant'))
            ->orderBy('id')
            ->each(function (User $user) use ($today, &$sent): void {
                if ($user->preference('emailReviewDigest') && $this->sendReviewDigest($user, $today)) {
                    $sent['review']++;
                }

                if ($user->preference('emailDeadlineReminders')) {
                    $sent['deadline'] += $this->sendDeadlineReminders($user, $today);
                }
            });

        return $sent;
    }

    /** At most one digest per ISO week, and only when something needs attention. */
    private function sendReviewDigest(User $user, CarbonImmutable $today): bool
    {
        $counts = Document::query()
            ->where('user_id', $user->getKey())
            ->whereIn('status', self::ATTENTION_STATUSES)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        if ($counts->sum() === 0) {
            return false;
        }

        if (! $this->claim($user, 'review_digest', $today->format('o-\WW'))) {
            return false;
        }

        $lines = [];
        foreach ([
            'review_required' => 'need your review',
            'processing_failed' => 'failed processing',
            'awaiting_financial_year' => 'need a financial year',
        ] as $status => $label) {
            $count = (int) ($counts[$status] ?? 0);
            if ($count > 0) {
                $lines[] = $count.' '.($count === 1 ? 'document' : 'documents').' '.$label.'.';
            }
        }

        Mail::to($user->email)->send(new AccountNotificationMail(
            mailSubject: 'TaxVault: documents need your attention',
            greetingName: $user->display_name ?? $user->email,
            lines: $lines,
            link: $this->appUrl('/documents?status='.($counts->has('review_required') ? 'review_required' : $counts->keys()->first())),
            linkLabel: 'Open documents',
            unsubscribeUrl: $this->unsubscribeUrl($user),
        ));

        return true;
    }

    private function sendDeadlineReminders(User $user, CarbonImmutable $today): int
    {
        $sent = 0;

        foreach ($this->deadlines($user) as $label => $deadline) {
            $daysLeft = (int) $today->diffInDays($deadline, false);

            if (! in_array($daysLeft, self::REMINDER_DAYS, true) || $this->isCompleted($user, $deadline)) {
                continue;
            }

            if (! $this->claim($user, 'deadline', $deadline->toDateString().':'.$daysLeft)) {
                continue;
            }

            $when = $deadline->format('j F Y');
            $title = "Tax return due {$when}";

            Mail::to($user->email)->send(new AccountNotificationMail(
                mailSubject: "TaxVault reminder: {$title} ({$daysLeft} ".($daysLeft === 1 ? 'day' : 'days').' left)',
                greetingName: $user->display_name ?? $user->email,
                lines: [
                    "Your {$label} lodgement date is {$when}, {$daysLeft} ".($daysLeft === 1 ? 'day' : 'days').' away.',
                    'Check that your documents are reviewed and your transactions have a tax return item, then share the accountant pack.',
                    'A calendar file is attached so you can add this date to your own calendar.',
                ],
                link: $this->appUrl('/tax-return'),
                linkLabel: 'Open accountant pack',
                unsubscribeUrl: $this->unsubscribeUrl($user),
                calendarFile: CalendarReminder::allDayEvent("deadline-{$user->getKey()}-{$deadline->toDateString()}", $title, 'Prepared with TaxVault. Confirm the date with the ATO or your tax agent.', $deadline),
            ));
            $sent++;
        }

        return $sent;
    }

    /**
     * The date the user chose, or 31 October after each financial year ends.
     *
     * @return array<string, CarbonImmutable>
     */
    public function deadlines(User $user): array
    {
        $custom = $user->preference('lodgementDeadline');

        if (is_string($custom) && $custom !== '') {
            return ['chosen' => CarbonImmutable::parse($custom)->startOfDay()];
        }

        $deadlines = [];

        FinancialYear::query()->where('user_id', $user->getKey())->get()->each(function (FinancialYear $year) use (&$deadlines): void {
            $deadlines['FY '.$year->year_code] = CarbonImmutable::create($year->end_date->year, 10, 31)->startOfDay();
        });

        return $deadlines;
    }

    /** Deadlines the user marked as done (stored by date) get no further reminders. */
    public function isCompleted(User $user, CarbonImmutable $deadline): bool
    {
        $done = $user->preference('completedDeadlines');

        return is_array($done) && in_array($deadline->toDateString(), $done, true);
    }

    /** Records the send so the same reminder is never sent twice, even if the command runs again. */
    private function claim(User $user, string $kind, string $key): bool
    {
        return DB::table('email_notification_logs')->insertOrIgnore([
            'user_id' => $user->getKey(),
            'kind' => $kind,
            'dedupe_key' => $key,
            'sent_at' => now(),
        ]) === 1;
    }

    public function unsubscribeUrl(User $user): string
    {
        return URL::signedRoute('email.unsubscribe', ['user' => $user->getKey()]);
    }

    private function appUrl(string $path): string
    {
        return rtrim((string) config('app.url'), '/').$path;
    }
}
