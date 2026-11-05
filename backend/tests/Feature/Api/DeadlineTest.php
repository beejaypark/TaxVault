<?php

namespace Tests\Feature\Api;

use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Notifications\Services\EmailNotificationSender;
use App\Domain\Transactions\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DeadlineTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-09-30 10:00:00');
        $this->user = User::factory()->create();
        $this->year('2025-26', '2025-07-01', '2026-06-30');
        $this->year('2024-25', '2024-07-01', '2025-06-30');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function year(string $code, string $start, string $end): FinancialYear
    {
        return FinancialYear::query()->create([
            'user_id' => $this->user->id, 'year_code' => $code, 'start_date' => $start, 'end_date' => $end, 'status' => 'active',
        ]);
    }

    private function write(string $method, string $uri)
    {
        return $this->actingAs($this->user)->withSession(['_token' => 't'])->withHeader('X-CSRF-TOKEN', 't')->{$method}($uri);
    }

    public function test_lists_only_upcoming_deadlines_with_open_counts(): void
    {
        $year = FinancialYear::query()->where('year_code', '2025-26')->first();
        Transaction::query()->create([
            'user_id' => $this->user->id, 'financial_year_id' => $year->id, 'transaction_date' => '2025-09-01',
            'direction' => 'expense', 'amount' => '10.00', 'description' => 'x', 'source_system' => 'manual',
        ]);

        $data = $this->actingAs($this->user)->getJson('/api/deadlines')->assertOk()->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('FY 2025-26', $data[0]['label']);
        $this->assertSame('2026-10-31', $data[0]['date']);
        $this->assertSame(31, $data[0]['days_left']);
        $this->assertSame('2026-06-30', $data[0]['financial_year_end']);
        $this->assertSame(0, $data[0]['days_to_year_end']);
        $this->assertSame(1, $data[0]['uncategorised_transactions']);
        $this->assertFalse($data[0]['completed']);
    }

    public function test_completed_deadline_can_be_reopened_and_stops_reminders(): void
    {
        $this->user->forceFill(['preferences' => ['emailDeadlineReminders' => true]])->save();
        $this->write('putJson', '/api/deadlines/2026-10-31/complete')->assertOk();

        $this->assertTrue($this->actingAs($this->user)->getJson('/api/deadlines')->json('data.0.completed'));

        Mail::fake();
        $sent = app(EmailNotificationSender::class)->run(CarbonImmutable::parse('2026-10-24'));
        $this->assertSame(0, $sent['deadline']);

        $this->write('deleteJson', '/api/deadlines/2026-10-31/complete')->assertOk();
        $this->assertFalse($this->actingAs($this->user)->getJson('/api/deadlines')->json('data.0.completed'));
    }

    public function test_rejects_a_malformed_date(): void
    {
        $this->write('putJson', '/api/deadlines/not-a-date/complete')->assertNotFound();
    }
}
