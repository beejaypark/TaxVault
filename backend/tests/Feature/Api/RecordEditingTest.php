<?php

namespace Tests\Feature\Api;

use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Investments\Models\Investment;
use App\Domain\Properties\Models\Property;
use App\Domain\Transactions\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecordEditingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private FinancialYear $year;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->year = $this->makeYear('2025-26', '2025-07-01', '2026-06-30');
    }

    private function makeYear(string $code, string $start, string $end): FinancialYear
    {
        return FinancialYear::query()->create([
            'user_id' => $this->owner->id,
            'year_code' => $code,
            'start_date' => $start,
            'end_date' => $end,
            'status' => 'active',
        ]);
    }

    private function createTransaction(): string
    {
        return $this->actingAs($this->owner)->postJson('/api/transactions', [
            'transaction_date' => '2025-09-01',
            'direction' => 'income',
            'amount' => '100.00',
            'description' => 'Interest',
        ])->assertCreated()->json('data.id');
    }

    public function test_transaction_can_be_edited_and_moves_between_years_and_ledgers(): void
    {
        $next = $this->makeYear('2026-27', '2026-07-01', '2027-06-30');
        $id = $this->createTransaction();

        $this->actingAs($this->owner)->putJson("/api/transactions/{$id}", [
            'transaction_date' => '2026-08-01',
            'direction' => 'expense',
            'amount' => '55.50',
            'description' => 'Fee',
        ])->assertOk()->assertJsonPath('data.financial_year_id', $next->id);

        $this->assertSame(0, DB::table('income')->where('transaction_id', $id)->count());
        $this->assertSame(1, DB::table('expenses')->where('transaction_id', $id)->count());
        $this->assertSame('55.50', DB::table('expenses')->where('transaction_id', $id)->value('amount'));
    }

    public function test_transaction_edit_rejects_a_date_outside_any_year(): void
    {
        $id = $this->createTransaction();

        $this->actingAs($this->owner)->putJson("/api/transactions/{$id}", [
            'transaction_date' => '2031-01-01',
            'direction' => 'income',
            'amount' => '1',
            'description' => 'x',
        ])->assertUnprocessable();
    }

    public function test_transaction_can_be_deleted_with_its_ledger_rows(): void
    {
        $id = $this->createTransaction();

        $this->actingAs($this->owner)->deleteJson("/api/transactions/{$id}")->assertOk();

        $this->assertNull(Transaction::query()->find($id));
        $this->assertSame(0, DB::table('income')->where('transaction_id', $id)->count());
    }

    public function test_another_owner_cannot_touch_a_transaction(): void
    {
        $id = $this->createTransaction();

        $this->actingAs(User::factory()->create())->deleteJson("/api/transactions/{$id}")->assertNotFound();
    }

    public function test_empty_financial_year_can_be_renamed_and_deleted_but_used_one_cannot(): void
    {
        $empty = $this->makeYear('2030-31', '2030-07-01', '2031-06-30');

        $this->actingAs($this->owner)->patchJson("/api/financial-years/{$empty->id}", ['year_code' => '2031-32'])
            ->assertOk()
            ->assertJsonPath('data.start_date', '2031-07-01T00:00:00.000000Z');

        $this->actingAs($this->owner)->deleteJson("/api/financial-years/{$empty->id}")->assertOk();

        $this->createTransaction();

        $this->actingAs($this->owner)->patchJson("/api/financial-years/{$this->year->id}", ['year_code' => '2024-25'])
            ->assertUnprocessable();
        $this->actingAs($this->owner)->patchJson("/api/financial-years/{$this->year->id}", ['status' => 'inactive'])
            ->assertOk()->assertJsonPath('data.status', 'inactive');
    }

    public function test_deleting_a_financial_year_removes_all_of_its_records_but_not_other_years(): void
    {
        $other = $this->makeYear('2026-27', '2026-07-01', '2027-06-30');
        $this->createTransaction();
        $keep = $this->actingAs($this->owner)->postJson('/api/transactions', [
            'transaction_date' => '2026-09-01',
            'direction' => 'expense',
            'amount' => '10.00',
            'description' => 'Kept',
        ])->assertCreated()->json('data.id');

        $this->actingAs($this->owner)->deleteJson("/api/financial-years/{$this->year->id}")->assertOk();

        $this->assertNull(FinancialYear::query()->find($this->year->id));
        $this->assertSame(0, DB::table('transactions')->where('financial_year_id', $this->year->id)->count());
        $this->assertSame(0, DB::table('income')->where('financial_year_id', $this->year->id)->count());
        $this->assertNotNull(Transaction::query()->find($keep));
        $this->assertNotNull(FinancialYear::query()->find($other->id));
    }

    public function test_deleting_a_financial_year_removes_it_from_accountant_grants(): void
    {
        $other = $this->makeYear('2026-27', '2026-07-01', '2027-06-30');
        $grant = \App\Models\AccountantGrant::query()->create([
            'owner_id' => $this->owner->id,
            'accountant_user_id' => User::factory()->create()->id,
            'name' => 'Acc',
            'email' => 'acc@example.test',
            'sections' => ['documents'],
            'financial_year_ids' => [$this->year->id, $other->id],
        ]);

        $this->actingAs($this->owner)->deleteJson("/api/financial-years/{$this->year->id}")->assertOk();

        $this->assertSame([$other->id], $grant->refresh()->financial_year_ids);
    }

    public function test_property_and_investment_can_be_edited_and_deleted(): void
    {
        $property = $this->actingAs($this->owner)->postJson('/api/properties', [
            'address_line_1' => '1 Test St',
        ])->assertCreated()->json('data.id');

        $this->actingAs($this->owner)->putJson("/api/properties/{$property}", [
            'address_line_1' => '2 Test St',
            'suburb' => 'Sydney',
        ])->assertOk()->assertJsonPath('data.address_line_1', '2 Test St');

        $investment = $this->actingAs($this->owner)->postJson('/api/investments', [
            'investment_type' => 'shares',
            'acquisition_date' => '2025-08-01',
            'property_id' => $property,
        ])->assertCreated()->json('data.id');

        $this->actingAs($this->owner)->deleteJson("/api/properties/{$property}")->assertStatus(409);

        $this->actingAs($this->owner)->putJson("/api/investments/{$investment}", [
            'investment_type' => 'etf',
            'acquisition_date' => '2025-08-02',
            'cost_base' => '10',
        ])->assertOk()->assertJsonPath('data.investment_type', 'etf');

        $this->actingAs($this->owner)->deleteJson("/api/investments/{$investment}")->assertOk();
        $this->actingAs($this->owner)->deleteJson("/api/properties/{$property}")->assertOk();

        $this->assertNull(Investment::query()->find($investment));
    }

    public function test_property_value_mortgage_and_monthly_interest(): void
    {
        $property = $this->actingAs($this->owner)->postJson('/api/properties', [
            'address_line_1' => '1 Test St',
        ])->assertCreated()->json('data.id');

        $this->actingAs($this->owner)->putJson("/api/properties/{$property}", [
            'address_line_1' => '1 Test St',
            'current_value' => '950000',
            'current_value_date' => '2026-11-01',
        ])->assertOk()->assertJsonPath('data.current_value', '950000.00');

        $mortgage = $this->actingAs($this->owner)->postJson("/api/properties/{$property}/mortgages", [
            'bank_name' => 'Commonwealth Bank',
            'loan_type' => 'variable',
            'loan_amount' => '600000',
            'interest_rate' => '6.19',
        ])->assertCreated()->json('data.id');

        $this->actingAs($this->owner)->putJson("/api/mortgages/{$mortgage}/interest", [
            'month' => '2026-09', 'interest_amount' => '3100.50',
        ])->assertOk();
        $this->actingAs($this->owner)->putJson("/api/mortgages/{$mortgage}/interest", [
            'month' => '2026-09', 'interest_amount' => '3000',
        ])->assertOk()->assertJsonCount(1, 'data.interest_entries');

        $this->actingAs($this->owner)->getJson('/api/properties')
            ->assertOk()->assertJsonPath('data.0.mortgages.0.bank_name', 'Commonwealth Bank');

        $this->actingAs(User::factory()->create())->putJson("/api/mortgages/{$mortgage}", [
            'bank_name' => 'X', 'loan_type' => 'fixed', 'loan_amount' => '1',
        ])->assertNotFound();

        $this->actingAs($this->owner)->deleteJson("/api/properties/{$property}")->assertOk();
        $this->assertSame(0, DB::table('mortgages')->count());
        $this->assertSame(0, DB::table('mortgage_interest_entries')->count());
    }

    public function test_mortgage_rate_history_is_tracked_and_keeps_current_rate_in_sync(): void
    {
        $property = $this->actingAs($this->owner)->postJson('/api/properties', ['address_line_1' => '1 Test St'])
            ->assertCreated()->json('data.id');
        $mortgage = $this->actingAs($this->owner)->postJson("/api/properties/{$property}/mortgages", [
            'bank_name' => 'ANZ', 'loan_type' => 'variable', 'loan_amount' => '600000',
            'interest_rate' => '5.50', 'start_date' => '2024-01-01',
        ])->assertCreated()->assertJsonCount(1, 'data.rate_changes')->json('data.id');

        $this->actingAs($this->owner)->putJson("/api/mortgages/{$mortgage}/rates", ['effective_date' => '2025-03-01', 'rate' => '6.25'])
            ->assertOk()->assertJsonCount(2, 'data.rate_changes')->assertJsonPath('data.interest_rate', '6.250');
        $this->actingAs($this->owner)->putJson("/api/mortgages/{$mortgage}/rates", ['effective_date' => '2025-03-01', 'rate' => '6.30'])
            ->assertOk()->assertJsonCount(2, 'data.rate_changes');
        $this->actingAs($this->owner)->putJson("/api/mortgages/{$mortgage}/rates", ['effective_date' => '2999-01-01', 'rate' => '1'])
            ->assertOk()->assertJsonPath('data.interest_rate', '6.300');
        $this->actingAs($this->owner)->putJson("/api/mortgages/{$mortgage}/rates", ['effective_date' => '2025-03-01', 'rate' => '101'])
            ->assertUnprocessable();

        $latest = $this->actingAs($this->owner)->putJson("/api/mortgages/{$mortgage}", [
            'bank_name' => 'ANZ', 'loan_type' => 'variable', 'loan_amount' => '600000', 'interest_rate' => '6.00',
        ])->assertOk()->assertJsonPath('data.interest_rate', '6.000')->assertJsonCount(4, 'data.rate_changes');

        $entry = $latest->json('data.rate_changes.0.id');
        $this->actingAs(User::factory()->create())->deleteJson("/api/mortgages/{$mortgage}/rates/{$entry}")->assertNotFound();
        $this->actingAs($this->owner)->deleteJson("/api/mortgages/{$mortgage}/rates/{$entry}")
            ->assertOk()->assertJsonCount(3, 'data.rate_changes');

        $this->actingAs($this->owner)->deleteJson("/api/properties/{$property}")->assertOk();
        $this->assertSame(0, DB::table('mortgage_rate_changes')->count());
    }

    public function test_rental_items_roll_up_into_property_summary(): void
    {
        $property = $this->actingAs($this->owner)->postJson('/api/properties', [
            'address_line_1' => '1 Test St',
        ])->assertCreated()->json('data.id');

        $post = fn (array $extra) => $this->actingAs($this->owner)->postJson('/api/transactions', [
            'transaction_date' => '2025-09-01',
            'amount' => '100.00',
            'description' => 'Item',
            ...$extra,
        ]);

        $post(['direction' => 'income', 'amount' => '2000', 'ato_category' => 'rental_income', 'property_id' => $property])->assertCreated();
        $post(['direction' => 'expense', 'amount' => '300', 'ato_category' => 'rental_council_rates', 'property_id' => $property])->assertCreated();
        $post(['direction' => 'expense', 'amount' => '50', 'ato_category' => 'rental_council_rates', 'property_id' => $property])->assertCreated();
        $post(['direction' => 'expense', 'amount' => '70', 'ato_category' => 'work_other'])->assertCreated();

        $post(['direction' => 'expense', 'ato_category' => 'rental_water'])->assertUnprocessable();
        $post(['direction' => 'income', 'ato_category' => 'rental_water', 'property_id' => $property])->assertUnprocessable();
        $post(['direction' => 'expense', 'ato_category' => 'bogus'])->assertUnprocessable();
        $post(['direction' => 'expense', 'ato_category' => 'rental_water', 'property_id' => '00000000-0000-0000-0000-000000000000'])->assertUnprocessable();

        $mortgage = $this->actingAs($this->owner)->postJson("/api/properties/{$property}/mortgages", [
            'bank_name' => 'ANZ', 'loan_type' => 'variable', 'loan_amount' => '500000',
        ])->assertCreated()->json('data.id');
        foreach (['2025-09' => '1000', '2026-02' => '1500', '2026-08' => '999'] as $month => $amount) {
            $this->actingAs($this->owner)->putJson("/api/mortgages/{$mortgage}/interest", [
                'month' => $month, 'interest_amount' => $amount,
            ])->assertOk();
        }

        $summary = $this->actingAs($this->owner)
            ->getJson("/api/properties/{$property}/rental-summary?financial_year_id={$this->year->id}")
            ->assertOk()
            ->assertJsonPath('data.total_income', 2000)
            ->assertJsonPath('data.total_expenses', 2850)
            ->assertJsonPath('data.net_rental_result', -850)
            ->assertJsonPath('data.interest_from_mortgage_records', 2500);

        $council = collect($summary->json('data.expenses'))->firstWhere('category', 'rental_council_rates');
        $this->assertSame(350, (int) $council['amount']);
        $this->assertSame(2, $council['records']);

        $this->actingAs(User::factory()->create())
            ->getJson("/api/properties/{$property}/rental-summary?financial_year_id={$this->year->id}")
            ->assertNotFound();

        $this->actingAs($this->owner)->deleteJson("/api/properties/{$property}")->assertOk();
        $this->assertSame(4, DB::table('transactions')->count());
    }

    public function test_main_residence_records_are_left_out_of_the_tax_return(): void
    {
        $home = $this->actingAs($this->owner)->postJson('/api/properties', [
            'address_line_1' => '1 Home St', 'is_main_residence' => true,
        ])->assertCreated()->assertJsonPath('data.is_main_residence', true)->json('data.id');
        $rental = $this->actingAs($this->owner)->postJson('/api/properties', ['address_line_1' => '2 Rental St'])
            ->assertCreated()->assertJsonPath('data.is_main_residence', false)->json('data.id');

        $post = fn (array $extra) => $this->actingAs($this->owner)->postJson('/api/transactions', [
            'transaction_date' => '2025-09-01', 'direction' => 'expense', 'amount' => '100', 'description' => 'Item', ...$extra,
        ])->assertCreated();

        $post(['property_id' => $home]);
        $post(['property_id' => $home, 'ato_category' => 'work_other']);
        $post(['property_id' => $rental, 'ato_category' => 'rental_water']);

        $mortgage = $this->actingAs($this->owner)->postJson("/api/properties/{$home}/mortgages", [
            'bank_name' => 'ANZ', 'loan_type' => 'variable', 'loan_amount' => '500000',
        ])->assertCreated()->json('data.id');
        $this->actingAs($this->owner)->putJson("/api/mortgages/{$mortgage}/interest", [
            'month' => '2025-09', 'interest_amount' => '1000',
        ])->assertOk();

        $summary = $this->actingAs($this->owner)
            ->getJson("/api/financial-years/{$this->year->id}/return-summary")->assertOk();

        $this->assertCount(1, $summary->json('data.lines'));
        $this->assertSame(0, $summary->json('data.uncategorised.expense.records'));
        $this->assertSame([$rental], array_column($summary->json('data.rental'), 'property_id'));

        $this->actingAs($this->owner)->putJson("/api/properties/{$home}", ['is_main_residence' => false])
            ->assertOk()->assertJsonPath('data.is_main_residence', false);
        $this->actingAs($this->owner)->getJson("/api/financial-years/{$this->year->id}/return-summary")
            ->assertOk()->assertJsonPath('data.uncategorised.expense.records', 1);
    }

    public function test_bulk_categorise_and_csv_import_with_deduplication(): void
    {
        $property = $this->actingAs($this->owner)->postJson('/api/properties', [
            'address_line_1' => '1 Test St',
        ])->assertCreated()->json('data.id');
        $a = $this->createTransaction();
        $b = $this->createTransaction();

        $this->actingAs($this->owner)->postJson('/api/transactions/bulk-categorise', [
            'ids' => [$a, $b], 'ato_category' => 'bank_interest',
        ])->assertOk()->assertJsonPath('data.updated', 2);
        $this->assertSame(2, DB::table('transactions')->where('ato_category', 'bank_interest')->count());

        $this->actingAs($this->owner)->postJson('/api/transactions/bulk-categorise', [
            'ids' => [$a], 'ato_category' => 'work_other',
        ])->assertOk()->assertJsonPath('data.updated', 0)->assertJsonPath('data.skipped', 1);

        $this->actingAs($this->owner)->postJson('/api/transactions/bulk-categorise', [
            'ids' => [$a], 'ato_category' => 'rental_income',
        ])->assertUnprocessable();

        $this->actingAs(User::factory()->create())->postJson('/api/transactions/bulk-categorise', [
            'ids' => [$a], 'ato_category' => 'bank_interest',
        ])->assertOk()->assertJsonPath('data.updated', 0);

        $rows = [
            ['date' => '2025-10-01', 'description' => 'Coffee', 'amount' => -4.5],
            ['date' => '2025-10-01', 'description' => 'Coffee', 'amount' => -4.5],
            ['date' => '2025-10-02', 'description' => 'Rent in', 'amount' => 500, 'ato_category' => 'rental_income', 'property_id' => $property],
            ['date' => '2025-10-03', 'description' => 'Water', 'amount' => -30, 'ato_category' => 'rental_water'],
            ['date' => '2020-01-01', 'description' => 'Too old', 'amount' => -1],
        ];

        $this->actingAs($this->owner)->postJson('/api/transactions/import', ['rows' => $rows])
            ->assertOk()
            ->assertJsonPath('data.imported', 4)
            ->assertJsonPath('data.outside_financial_years', 1);
        $this->assertSame(1, DB::table('transactions')->where('ato_category', 'rental_income')->count());
        $this->assertSame(0, DB::table('transactions')->where('description', 'Water')->whereNotNull('ato_category')->count());

        $this->actingAs($this->owner)->postJson('/api/transactions/import', ['rows' => $rows])
            ->assertOk()
            ->assertJsonPath('data.imported', 0)
            ->assertJsonPath('data.duplicates', 4);
    }

    public function test_work_from_home_log_and_return_summary(): void
    {
        $this->actingAs($this->owner)->postJson('/api/work-from-home', ['work_date' => '2025-09-01', 'hours' => 8])->assertCreated();
        $this->actingAs($this->owner)->postJson('/api/work-from-home', ['work_date' => '2025-09-01', 'hours' => 6])->assertCreated();
        $this->actingAs($this->owner)->postJson('/api/work-from-home', ['work_date' => '2025-09-02', 'hours' => 4])->assertCreated();
        $this->actingAs($this->owner)->postJson('/api/work-from-home', ['work_date' => '2019-09-02', 'hours' => 4])->assertUnprocessable();

        $this->actingAs($this->owner)->getJson("/api/work-from-home?financial_year_id={$this->year->id}")
            ->assertOk()
            ->assertJsonPath('data.summary.hours', 10)
            ->assertJsonPath('data.summary.days', 2)
            ->assertJsonPath('data.summary.amount', 7)
            ->assertJsonCount(2, 'data.logs');

        $this->actingAs($this->owner)->putJson("/api/financial-years/{$this->year->id}/wfh-rate", ['rate' => 0.5])
            ->assertOk()->assertJsonPath('data.amount', 5);

        $this->actingAs($this->owner)->postJson('/api/transactions', [
            'transaction_date' => '2025-09-01', 'direction' => 'expense', 'amount' => '100', 'description' => 'Desk',
            'ato_category' => 'work_other',
        ])->assertCreated();
        $this->createTransaction();

        $summary = $this->actingAs($this->owner)->getJson("/api/financial-years/{$this->year->id}/return-summary")
            ->assertOk()
            ->assertJsonPath('data.totals.deductions', 105)
            ->assertJsonPath('data.uncategorised.income.records', 1);
        $this->assertSame(['work_other', 'work_from_home'], collect($summary->json('data.lines'))->pluck('category')->all());

        $this->actingAs(User::factory()->create())->getJson("/api/financial-years/{$this->year->id}/return-summary")->assertNotFound();

        $this->actingAs($this->owner)->deleteJson("/api/financial-years/{$this->year->id}")->assertOk();
        $this->assertSame(0, DB::table('work_from_home_logs')->count());
    }
}
