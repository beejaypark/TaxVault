<?php

namespace Tests\Unit\Domain\Evidence\Services;

use App\Domain\Evidence\Services\MissingEvidenceDetector;
use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Transactions\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MissingEvidenceDetectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_detects_transaction_without_evidence(): void
    {
        $user = User::factory()->create();

        $fy = FinancialYear::create([
            'user_id' => $user->id,
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
        ]);

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'financial_year_id' => $fy->id,
            'transaction_date' => '2026-03-15',
            'direction' => 'expense',
            'amount' => '125.00',
            'currency' => 'AUD',
        ]);

        $results = app(MissingEvidenceDetector::class)->detect($user, $fy);

        $this->assertCount(1, $results);
        $this->assertSame('transaction', $results[0]['record_type']);
        $this->assertSame($transaction->id, $results[0]['record_id']);
        $this->assertSame($fy->id, $results[0]['financial_year_id']);
        $this->assertSame('2025-26', $results[0]['financial_year_code']);
        $this->assertSame('missing_evidence', $results[0]['reason']);
    }

    public function test_it_is_scoped_to_the_selected_financial_year(): void
    {
        $user = User::factory()->create();

        $fy2025 = FinancialYear::create([
            'user_id' => $user->id,
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
        ]);

        $fy2026 = FinancialYear::create([
            'user_id' => $user->id,
            'year_code' => '2026-27',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'status' => 'active',
        ]);

        $first = Transaction::create([
            'user_id' => $user->id,
            'financial_year_id' => $fy2025->id,
            'transaction_date' => '2026-06-30',
            'direction' => 'expense',
            'amount' => '100.00',
            'currency' => 'AUD',
        ]);

        Transaction::create([
            'user_id' => $user->id,
            'financial_year_id' => $fy2026->id,
            'transaction_date' => '2026-07-01',
            'direction' => 'expense',
            'amount' => '200.00',
            'currency' => 'AUD',
        ]);

        $results = app(MissingEvidenceDetector::class)->detect($user, $fy2025);

        $this->assertCount(1, $results);
        $this->assertSame($first->id, $results[0]['record_id']);
        $this->assertSame('2025-26', $results[0]['financial_year_code']);
    }

    public function test_it_does_not_expose_another_users_record(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $fy = FinancialYear::create([
            'user_id' => $user->id,
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
        ]);

        Transaction::create([
            'user_id' => $otherUser->id,
            'financial_year_id' => $fy->id,
            'transaction_date' => '2026-03-15',
            'direction' => 'expense',
            'amount' => '50.00',
            'currency' => 'AUD',
        ]);

        $results = app(MissingEvidenceDetector::class)->detect($user, $fy);

        $this->assertCount(0, $results);
    }

    public function test_clean_financial_year_returns_no_missing_evidence(): void
    {
        $user = User::factory()->create();

        $fy = FinancialYear::create([
            'user_id' => $user->id,
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
        ]);

        $results = app(MissingEvidenceDetector::class)->detect($user, $fy);

        $this->assertCount(0, $results);
    }
}
