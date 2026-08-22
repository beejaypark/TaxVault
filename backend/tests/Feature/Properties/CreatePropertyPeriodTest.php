<?php

namespace Tests\Feature\Properties;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreatePropertyPeriodTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_property_period(): void
    {
        $user = User::factory()->create([
            'identity_provider' => 'test',
            'provider_subject' => 'property-period-create',
            'email' => 'property-period-create@example.com',
            'display_name' => 'Property Period Create',
            'status' => 'active',
        ]);

        $property = $user->properties()->create([
            'reference_code' => 'PERIOD-PROPERTY',
            'country_code' => 'AU',
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/properties/{$property->id}/periods", [
                'period_start' => '2025-07-01',
                'period_end' => '2026-06-30',
                'use_type' => 'investment',
                'ownership_percentage' => 100,
                'provenance' => [
                    'source' => 'test',
                ],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.property_id', $property->id)
            ->assertJsonPath('data.use_type', 'investment');

        $this->assertDatabaseHas('property_periods', [
            'property_id' => $property->id,
            'period_start' => '2025-07-01',
            'period_end' => '2026-06-30',
            'use_type' => 'investment',
        ]);
    }

    public function test_user_cannot_create_period_for_another_users_property(): void
    {
        $user = User::factory()->create([
            'identity_provider' => 'test',
            'provider_subject' => 'property-period-owner',
            'email' => 'property-period-owner@example.com',
            'display_name' => 'Property Period Owner',
            'status' => 'active',
        ]);

        $otherUser = User::factory()->create([
            'identity_provider' => 'test',
            'provider_subject' => 'property-period-other',
            'email' => 'property-period-other@example.com',
            'display_name' => 'Property Period Other',
            'status' => 'active',
        ]);

        $property = $otherUser->properties()->create([
            'reference_code' => 'OTHER-PROPERTY',
            'country_code' => 'AU',
        ]);

        $this->actingAs($user)
            ->postJson("/api/properties/{$property->id}/periods", [
                'period_start' => '2025-07-01',
                'period_end' => '2026-06-30',
                'use_type' => 'investment',
                'ownership_percentage' => 100,
            ])
            ->assertNotFound();
    }

    public function test_property_period_validation_rejects_invalid_ownership(): void
    {
        $user = User::factory()->create([
            'identity_provider' => 'test',
            'provider_subject' => 'property-period-validation',
            'email' => 'property-period-validation@example.com',
            'display_name' => 'Property Period Validation',
            'status' => 'active',
        ]);

        $property = $user->properties()->create([
            'reference_code' => 'VALIDATION-PROPERTY',
            'country_code' => 'AU',
        ]);

        $this->actingAs($user)
            ->postJson("/api/properties/{$property->id}/periods", [
                'period_start' => '2025-07-01',
                'period_end' => '2026-06-30',
                'use_type' => 'investment',
                'ownership_percentage' => 100.01,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'ownership_percentage',
            ]);
    }

    public function test_property_period_validation_rejects_end_before_start(): void
    {
        $user = User::factory()->create([
            'identity_provider' => 'test',
            'provider_subject' => 'property-period-dates',
            'email' => 'property-period-dates@example.com',
            'display_name' => 'Property Period Dates',
            'status' => 'active',
        ]);

        $property = $user->properties()->create([
            'reference_code' => 'DATES-PROPERTY',
            'country_code' => 'AU',
        ]);

        $this->actingAs($user)
            ->postJson("/api/properties/{$property->id}/periods", [
                'period_start' => '2026-07-01',
                'period_end' => '2026-06-30',
                'use_type' => 'investment',
                'ownership_percentage' => 100,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'period_end',
            ]);
    }

    public function test_property_period_database_prevents_overlap(): void
    {
        $user = User::factory()->create([
            'identity_provider' => 'test',
            'provider_subject' => 'property-period-overlap',
            'email' => 'property-period-overlap@example.com',
            'display_name' => 'Property Period Overlap',
            'status' => 'active',
        ]);

        $property = $user->properties()->create([
            'reference_code' => 'OVERLAP-PROPERTY',
            'country_code' => 'AU',
        ]);

        $this->actingAs($user)
            ->postJson("/api/properties/{$property->id}/periods", [
                'period_start' => '2025-07-01',
                'period_end' => '2026-06-30',
                'use_type' => 'investment',
                'ownership_percentage' => 100,
            ])
            ->assertCreated();

        $this->actingAs($user)
            ->postJson("/api/properties/{$property->id}/periods", [
                'period_start' => '2026-01-01',
                'period_end' => '2026-12-31',
                'use_type' => 'private',
                'ownership_percentage' => 100,
            ])
            ->assertStatus(500);
    }
}
