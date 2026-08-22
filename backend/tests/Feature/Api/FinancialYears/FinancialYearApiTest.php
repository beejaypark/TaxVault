<?php

namespace Tests\Feature\Api\FinancialYears;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialYearApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_an_australian_financial_year(): void
    {
        $user = User::factory()->create([
            'identity_provider' => 'test',
            'provider_subject' => 'fy-api-create-user',
            'email' => 'fy-api-create@example.com',
            'display_name' => 'FY API Create User',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->postJson('/api/financial-years', [
            'year_code' => '2026-27',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.year_code', '2026-27')
            ->assertJsonPath('data.user_id', $user->id);

        $this->assertDatabaseHas('financial_years', [
            'user_id' => $user->id,
            'year_code' => '2026-27',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'status' => 'active',
        ]);
    }

    public function test_invalid_australian_financial_year_dates_are_rejected(): void
    {
        $user = User::factory()->create([
            'identity_provider' => 'test',
            'provider_subject' => 'fy-api-invalid-user',
            'email' => 'fy-api-invalid@example.com',
            'display_name' => 'FY API Invalid User',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->postJson('/api/financial-years', [
            'year_code' => '2026-27',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseMissing('financial_years', [
            'user_id' => $user->id,
            'year_code' => '2026-27',
        ]);
    }

    public function test_user_can_only_see_their_own_financial_years(): void
    {
        $user = User::factory()->create([
            'identity_provider' => 'test',
            'provider_subject' => 'fy-api-owner',
            'email' => 'fy-api-owner@example.com',
            'display_name' => 'FY API Owner',
            'status' => 'active',
        ]);

        $otherUser = User::factory()->create([
            'identity_provider' => 'test',
            'provider_subject' => 'fy-api-other',
            'email' => 'fy-api-other@example.com',
            'display_name' => 'FY API Other',
            'status' => 'active',
        ]);

        $user->financialYears()->create([
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
        ]);

        $otherUser->financialYears()->create([
            'year_code' => '2026-27',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->getJson('/api/financial-years');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.year_code', '2025-26')
            ->assertJsonPath('data.0.user_id', $user->id);
    }

    public function test_unauthenticated_user_cannot_access_financial_years(): void
    {
        $this->getJson('/api/financial-years')
            ->assertUnauthorized();

        $this->postJson('/api/financial-years', [
            'year_code' => '2026-27',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
        ])->assertUnauthorized();
    }

    public function test_required_fields_are_validated(): void
    {
        $user = User::factory()->create([
            'identity_provider' => 'test',
            'provider_subject' => 'fy-api-validation',
            'email' => 'fy-api-validation@example.com',
            'display_name' => 'FY API Validation',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/financial-years', []);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'year_code',
                'start_date',
                'end_date',
            ]);
    }
}
