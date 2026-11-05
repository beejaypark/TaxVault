<?php

namespace Tests\Feature\Notifications;

use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Notifications\Services\EmailNotificationSender;
use App\Mail\AccountNotificationMail;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function owner(array $preferences): User
    {
        return User::factory()->create(['preferences' => $preferences]);
    }

    private function year(User $user): FinancialYear
    {
        return FinancialYear::query()->create([
            'user_id' => $user->id,
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
        ]);
    }

    private function document(User $user, FinancialYear $year, string $status): void
    {
        $type = DocumentType::query()->first()
            ?? DocumentType::query()->create(['code' => 'other', 'name' => 'Other']);

        Document::query()->create([
            'user_id' => $user->id,
            'financial_year_id' => $year->id,
            'document_type_id' => $type->id,
            'storage_disk' => 'local',
            'object_key' => 'k/'.uniqid(),
            'content_sha256' => hash('sha256', uniqid()),
            'original_filename' => 'a.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 10,
            'uploaded_at' => now(),
            'status' => $status,
        ]);
    }

    private function sessionToken(): void
    {
        $this->withSession(['_token' => 't'])->withHeader('X-CSRF-TOKEN', 't');
    }

    public function test_review_digest_is_sent_once_a_week_only_when_needed(): void
    {
        Mail::fake();
        $user = $this->owner(['emailReviewDigest' => true]);
        $sender = app(EmailNotificationSender::class);
        $today = CarbonImmutable::parse('2026-03-02');

        $this->assertSame(0, $sender->run($today)['review']);

        $year = $this->year($user);
        $this->document($user, $year, 'review_required');
        $this->document($user, $year, 'processing_failed');

        $this->assertSame(1, $sender->run($today)['review']);
        $this->assertSame(0, $sender->run($today->addDays(2))['review']);
        $this->assertSame(1, $sender->run($today->addDays(7))['review']);

        Mail::assertSent(AccountNotificationMail::class, fn ($mail) => $mail->hasTo($user->email)
            && in_array('1 document need your review.', $mail->lines, true));
    }

    public function test_users_who_did_not_opt_in_get_nothing(): void
    {
        Mail::fake();
        $user = $this->owner(['emailReviewDigest' => false, 'emailDeadlineReminders' => false]);
        $this->document($user, $this->year($user), 'review_required');

        $sent = app(EmailNotificationSender::class)->run(CarbonImmutable::parse('2026-10-24'));

        $this->assertSame(['review' => 0, 'deadline' => 0], $sent);
        Mail::assertNothingSent();
    }

    public function test_deadline_reminders_go_out_at_30_7_and_1_days_with_a_calendar_file(): void
    {
        Mail::fake();
        $user = $this->owner(['emailDeadlineReminders' => true]);
        $this->year($user);
        $sender = app(EmailNotificationSender::class);

        $this->assertSame(0, $sender->run(CarbonImmutable::parse('2026-09-30'))['deadline']);
        $this->assertSame(1, $sender->run(CarbonImmutable::parse('2026-10-01'))['deadline']);
        $this->assertSame(0, $sender->run(CarbonImmutable::parse('2026-10-02'))['deadline']);
        $this->assertSame(1, $sender->run(CarbonImmutable::parse('2026-10-24'))['deadline']);
        $this->assertSame(1, $sender->run(CarbonImmutable::parse('2026-10-30'))['deadline']);
        $this->assertSame(0, $sender->run(CarbonImmutable::parse('2026-10-31'))['deadline']);

        Mail::assertSent(AccountNotificationMail::class, 3);
        Mail::assertSent(AccountNotificationMail::class, fn ($mail) => str_contains((string) $mail->calendarFile, 'DTSTART;VALUE=DATE:20261031'));
    }

    public function test_the_same_reminder_is_not_resent_if_the_command_runs_twice(): void
    {
        Mail::fake();
        $this->year($this->owner(['emailDeadlineReminders' => true]));
        $sender = app(EmailNotificationSender::class);
        $day = CarbonImmutable::parse('2026-10-24');

        $this->assertSame(1, $sender->run($day)['deadline']);
        $this->assertSame(0, $sender->run($day)['deadline']);
    }

    public function test_a_chosen_lodgement_date_replaces_the_default(): void
    {
        $user = $this->owner(['emailDeadlineReminders' => true, 'lodgementDeadline' => '2027-05-15']);
        $this->year($user);

        $deadlines = app(EmailNotificationSender::class)->deadlines($user);

        $this->assertSame(['chosen'], array_keys($deadlines));
        $this->assertSame('2027-05-15', $deadlines['chosen']->toDateString());
    }

    public function test_accountants_are_never_emailed(): void
    {
        Mail::fake();
        User::factory()->create(['role' => 'accountant', 'preferences' => ['emailReviewDigest' => true, 'emailDeadlineReminders' => true]]);

        $this->assertSame(['review' => 0, 'deadline' => 0], app(EmailNotificationSender::class)->run(CarbonImmutable::parse('2026-10-24')));
    }

    public function test_unsubscribe_link_turns_off_email_without_signing_in(): void
    {
        $user = $this->owner(['emailReviewDigest' => true, 'emailDeadlineReminders' => true, 'notifyDocuments' => true]);

        $this->get(app(EmailNotificationSender::class)->unsubscribeUrl($user))->assertOk();

        $user->refresh();
        $this->assertFalse($user->preference('emailReviewDigest'));
        $this->assertFalse($user->preference('emailDeadlineReminders'));
        $this->assertTrue($user->preference('notifyDocuments'));
    }

    public function test_unsubscribe_rejects_unsigned_and_tampered_links(): void
    {
        $user = $this->owner(['emailReviewDigest' => true]);

        $this->get('/api/email/unsubscribe/'.$user->id)->assertForbidden();
        $this->get(URL::signedRoute('email.unsubscribe', ['user' => $user->id]).'x')->assertForbidden();
        $this->assertTrue($user->refresh()->preference('emailReviewDigest'));
    }

    public function test_test_email_is_sent_to_the_signed_in_user(): void
    {
        Mail::fake();
        $user = $this->owner([]);
        $this->sessionToken();

        $this->actingAs($user)->postJson('/api/auth/preferences/test-email')
            ->assertOk()
            ->assertJsonPath('data.sent_to', $user->email);

        Mail::assertSent(AccountNotificationMail::class, fn ($mail) => $mail->hasTo($user->email));
    }

    public function test_preferences_keep_other_email_settings_and_validate_the_date(): void
    {
        $user = $this->owner(['emailReviewDigest' => true]);
        $this->sessionToken();
        $base = ['notifyDocuments' => true, 'notifyUncategorised' => true, 'defaultFinancialYear' => 'current'];

        $this->actingAs($user)->putJson('/api/auth/preferences', $base + ['lodgementDeadline' => 'soon'])->assertUnprocessable();
        $this->actingAs($user)->putJson('/api/auth/preferences', $base + ['lodgementDeadline' => '2026-10-31'])
            ->assertOk()
            ->assertJsonPath('data.emailReviewDigest', true)
            ->assertJsonPath('data.lodgementDeadline', '2026-10-31');
    }
}
