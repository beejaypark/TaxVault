<?php

namespace Tests\Feature\Api;

use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Properties\Models\Mortgage;
use App\Domain\Properties\Models\Property;
use App\Domain\Transactions\Models\Transaction;
use App\Domain\Wealth\Models\AssetValuation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WealthTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-09-30 10:00:00');
        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function property(): Property
    {
        return Property::query()->create(['user_id' => $this->user->id, 'address_line_1' => '1 Test St', 'suburb' => 'Sydney', 'state' => 'NSW', 'postcode' => '2000', 'country_code' => 'AU']);
    }

    public function test_updating_a_property_value_records_a_dated_valuation(): void
    {
        $property = $this->property();

        $this->actingAs($this->user)->withSession(['_token' => 't'])->withHeader('X-CSRF-TOKEN', 't')
            ->putJson('/api/properties/'.$property->id, [
                'address_line_1' => '1 Test St', 'current_value' => 800000, 'current_value_date' => '2026-06-30',
            ])->assertOk();

        $this->assertDatabaseHas('asset_valuations', ['property_id' => $property->id, 'as_of' => '2026-06-30', 'value' => '800000.00']);
    }

    public function test_summary_reports_net_worth_history_and_cash_flow(): void
    {
        $property = $this->property();
        $property->update(['current_value' => 900000, 'current_value_date' => '2026-09-01']);
        AssetValuation::record($this->user->id, 'property_id', $property->id, 800000, '2026-06-30');
        AssetValuation::record($this->user->id, 'property_id', $property->id, 900000, '2026-09-01');
        Mortgage::query()->create(['user_id' => $this->user->id, 'property_id' => $property->id, 'bank_name' => 'B', 'loan_type' => 'variable', 'loan_amount' => 500000, 'interest_rate' => 6, 'start_date' => '2024-01-01']);

        $year = FinancialYear::query()->create(['user_id' => $this->user->id, 'year_code' => '2026-27', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'status' => 'active']);
        foreach ([['income', '5000.00'], ['expense', '2000.00']] as [$direction, $amount]) {
            Transaction::query()->create(['user_id' => $this->user->id, 'financial_year_id' => $year->id, 'transaction_date' => '2026-09-10', 'direction' => $direction, 'amount' => $amount, 'description' => 'x', 'source_system' => 'manual']);
        }

        $data = $this->actingAs($this->user)->getJson('/api/wealth')->assertOk()->json('data');

        $this->assertEquals(900000.0, $data['total_assets']);
        $this->assertEquals(500000.0, $data['total_liabilities']);
        $this->assertEquals(400000.0, $data['net_worth']);
        $this->assertEquals(6.0, $data['average_interest_rate']);

        $history = collect($data['history'])->keyBy('month');
        $this->assertEquals(300000.0, $history['2026-06']['net_worth']);
        $this->assertEquals(400000.0, $history['2026-09']['net_worth']);

        $this->assertCount(12, $data['cash_flow']);
        $september = collect($data['cash_flow'])->firstWhere('month', '2026-09');
        $this->assertEquals(5000.0, $september['income']);
        $this->assertEquals(2000.0, $september['expenses']);
    }

    public function test_summary_is_scoped_to_the_current_user(): void
    {
        $other = User::factory()->create();
        Property::query()->create(['user_id' => $other->id, 'address_line_1' => 'x', 'current_value' => 1000000, 'country_code' => 'AU']);

        $this->actingAs($this->user)->getJson('/api/wealth')->assertOk()->assertJsonPath('data.total_assets', 0);
    }
}
