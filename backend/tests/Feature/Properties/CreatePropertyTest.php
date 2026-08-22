<?php

namespace Tests\Feature\Properties;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreatePropertyTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_property(): void
    {
        $user = User::factory()->create([
            'identity_provider' => 'test',
            'provider_subject' => 'property-create-user',
            'email' => 'property-create@example.com',
            'display_name' => 'Property Create User',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->postJson('/api/properties', [
            'reference_code' => 'PROPERTY-001',
            'address_line_1' => '1 Test Street',
            'suburb' => 'Sydney',
            'state' => 'NSW',
            'postcode' => '2000',
            'country_code' => 'AU',
            'location_metadata' => [
                'source' => 'test',
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.reference_code', 'PROPERTY-001');

        $this->assertDatabaseHas('properties', [
            'user_id' => $user->id,
            'reference_code' => 'PROPERTY-001',
        ]);
    }

    public function test_user_can_only_see_their_own_properties(): void
    {
        $user = User::factory()->create([
            'identity_provider' => 'test',
            'provider_subject' => 'property-owner',
            'email' => 'property-owner@example.com',
            'display_name' => 'Property Owner',
            'status' => 'active',
        ]);

        $otherUser = User::factory()->create([
            'identity_provider' => 'test',
            'provider_subject' => 'property-other',
            'email' => 'property-other@example.com',
            'display_name' => 'Property Other',
            'status' => 'active',
        ]);

        $user->properties()->create([
            'reference_code' => 'OWN-PROPERTY',
            'country_code' => 'AU',
        ]);

        $otherUser->properties()->create([
            'reference_code' => 'OTHER-PROPERTY',
            'country_code' => 'AU',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/properties');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reference_code', 'OWN-PROPERTY');
    }

    public function test_unauthenticated_user_cannot_access_properties(): void
    {
        $this->getJson('/api/properties')
            ->assertUnauthorized();

        $this->postJson('/api/properties', [
            'reference_code' => 'UNAUTH',
        ])->assertUnauthorized();
    }

    public function test_property_validation_is_applied(): void
    {
        $user = User::factory()->create([
            'identity_provider' => 'test',
            'provider_subject' => 'property-validation',
            'email' => 'property-validation@example.com',
            'display_name' => 'Property Validation',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->postJson('/api/properties', [
                'reference_code' => str_repeat('X', 101),
                'country_code' => 'AUS',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'reference_code',
                'country_code',
            ]);
    }
}
