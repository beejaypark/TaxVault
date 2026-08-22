<?php

namespace Tests\Unit\Domain\Reporting\Services;

use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Reporting\Services\TaxYearCompletenessService;
use App\Domain\Transactions\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class TaxYearCompletenessServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_financial_year_completeness(): void
    {
        $user = User::factory()->create();

        $fy = FinancialYear::create([
            'user_id' => $user->id,
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
        ]);

        DB::table('income')->insert([
            'id' => (string) Str::uuid(),
            'transaction_id' => $this->createTransaction($user, $fy, 'income'),
            'user_id' => $user->id,
            'financial_year_id' => $fy->id,
            'amount' => 5000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $transactionId = $this->createTransaction(
            $user,
            $fy,
            'expense'
        );

        DB::table('expenses')->insert([
            'id' => (string) Str::uuid(),
            'transaction_id' => $transactionId,
            'user_id' => $user->id,
            'financial_year_id' => $fy->id,
            'amount' => 125,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = app(TaxYearCompletenessService::class)
            ->calculate($user, $fy);

        $this->assertSame('2025-26', $result->financialYearCode);
        $this->assertSame(1, $result->incomeRecords);
        $this->assertSame(1, $result->expenseRecords);
        $this->assertSame(2, $result->transactionRecords);
        $this->assertSame(0, $result->evidenceLinked);
        $this->assertSame(2, $result->evidenceMissing);
        $this->assertSame(0, $result->verifiedRecords);
        $this->assertSame(2, $result->unverifiedRecords);
        $this->assertCount(2, $result->gaps);
    }

    public function test_it_does_not_include_another_users_data(): void
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

        $otherFy = FinancialYear::create([
            'user_id' => $otherUser->id,
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
        ]);

        DB::table('income')->insert([
            'id' => (string) Str::uuid(),
            'transaction_id' => $this->createTransaction(
                $otherUser,
                $otherFy,
                'income'
            ),
            'user_id' => $otherUser->id,
            'financial_year_id' => $otherFy->id,
            'amount' => 99999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = app(TaxYearCompletenessService::class)
            ->calculate($user, $fy);

        $this->assertSame(0, $result->incomeRecords);
        $this->assertSame(0.0, $result->incomeAmount);
    }

    public function test_it_rejects_a_financial_year_belonging_to_another_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $fy = FinancialYear::create([
            'user_id' => $otherUser->id,
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
        ]);

        $this->expectException(NotFoundHttpException::class);

        app(TaxYearCompletenessService::class)
            ->calculate($user, $fy);
    }

    private function createTransaction(
        User $user,
        FinancialYear $fy,
        string $direction,
    ): string {
        return Transaction::create([
            'user_id' => $user->id,
            'financial_year_id' => $fy->id,
            'transaction_date' => '2026-03-15',
            'direction' => $direction,
            'amount' => '100.00',
            'currency' => 'AUD',
        ])->id;
    }
}
