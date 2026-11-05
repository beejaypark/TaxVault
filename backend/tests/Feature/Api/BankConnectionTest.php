<?php

namespace Tests\Feature\Api;

use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Transactions\Models\Transaction;
use App\Models\BankConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BankConnectionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        FinancialYear::query()->create([
            'user_id' => $this->user->id,
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
        ]);
    }

    private function send(string $uri)
    {
        return $this->actingAs($this->user)
            ->withSession(['_token' => 't'])
            ->withHeader('X-CSRF-TOKEN', 't')
            ->postJson($uri);
    }

    private function enable(): void
    {
        config(['services.basiq.api_key' => 'test-key', 'services.basiq.base_url' => 'https://basiq.test']);
    }

    public function test_status_reports_disabled_without_a_key(): void
    {
        config(['services.basiq.api_key' => null]);

        $this->actingAs($this->user)->getJson('/api/bank/status')
            ->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.connected', false);
    }

    public function test_connect_and_sync_are_unavailable_without_a_key(): void
    {
        config(['services.basiq.api_key' => null]);

        $this->send('/api/bank/connect')->assertNotFound();
        $this->send('/api/bank/sync')->assertNotFound();
    }

    public function test_connect_creates_a_provider_user_once_and_returns_the_consent_url(): void
    {
        $this->enable();
        Http::fake([
            'basiq.test/token' => Http::response(['access_token' => 'tok']),
            'basiq.test/users' => Http::response(['id' => 'bq-user-1']),
        ]);

        $url = $this->send('/api/bank/connect')->assertOk()->json('data.url');
        $this->send('/api/bank/connect')->assertOk();

        $this->assertStringContainsString('token=tok', $url);
        $this->assertSame(1, BankConnection::query()->where('user_id', $this->user->id)->count());
        Http::assertSentCount(4);
    }

    public function test_sync_imports_posted_transactions_once_and_skips_pending(): void
    {
        $this->enable();
        BankConnection::query()->create(['user_id' => $this->user->id, 'provider' => 'basiq', 'provider_user_id' => 'bq-user-1']);
        Http::fake([
            'basiq.test/token' => Http::response(['access_token' => 'tok']),
            'basiq.test/users/bq-user-1/transactions*' => Http::response(['data' => [
                ['id' => 't1', 'status' => 'posted', 'direction' => 'debit', 'amount' => '-42.10', 'description' => 'BUNNINGS', 'postDate' => '2025-09-01T00:00:00Z'],
                ['id' => 't2', 'status' => 'posted', 'direction' => 'credit', 'amount' => '1500.00', 'description' => 'RENT', 'postDate' => '2025-09-02T00:00:00Z'],
                ['id' => 't3', 'status' => 'pending', 'direction' => 'debit', 'amount' => '-9.00', 'description' => 'PENDING', 'postDate' => '2025-09-03T00:00:00Z'],
                ['id' => 't4', 'status' => 'posted', 'direction' => 'debit', 'amount' => '-5.00', 'description' => 'OLD', 'postDate' => '2024-01-01T00:00:00Z'],
            ]]),
        ]);

        $this->send('/api/bank/sync')->assertOk()
            ->assertJsonPath('data.imported', 2)
            ->assertJsonPath('data.outside_financial_years', 1);

        $this->send('/api/bank/sync')->assertOk()
            ->assertJsonPath('data.imported', 0)
            ->assertJsonPath('data.duplicates', 2);

        $expense = Transaction::query()->where('external_transaction_id', 'basiq:t1')->first();
        $this->assertSame('expense', $expense->direction);
        $this->assertSame('bank_feed', $expense->source_system);
        $this->assertNotNull(BankConnection::query()->first()->last_synced_at);
    }

    public function test_provider_failure_returns_502(): void
    {
        $this->enable();
        BankConnection::query()->create(['user_id' => $this->user->id, 'provider' => 'basiq', 'provider_user_id' => 'bq-user-1']);
        Http::fake(['basiq.test/*' => Http::response([], 500)]);

        $this->send('/api/bank/sync')->assertStatus(502);
    }
}
