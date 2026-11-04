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
        $this->assertNull(Property::query()->find($property));
    }
}
