<?php

namespace Tests\Feature\Investments;

use App\Domain\Properties\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateInvestmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_investment(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/investments', [
                'investment_type' => 'shares',
                'acquisition_date' => '2025-08-01',
                'quantity' => '100.00000000',
                'ownership_percentage' => '100.0000',
                'cost_base' => '15000.00',
                'incidental_costs' => '120.00',
                'proceeds' => '18000.00',
                'source_system' => 'manual',
                'external_id' => 'INV-001',
                'metadata' => [
                    'broker' => 'Example Broker',
                ],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.investment_type', 'shares')
            ->assertJsonPath('data.acquisition_date', '2025-08-01T00:00:00.000000Z');

        $this->assertDatabaseHas('investments', [
            'user_id' => $user->id,
            'investment_type' => 'shares',
            'external_id' => 'INV-001',
        ]);
    }

    public function test_user_can_create_property_linked_investment(): void
    {
        $user = User::factory()->create();

        $property = Property::create([
            'user_id' => $user->id,
            'reference_code' => 'PROP-001',
            'address_line_1' => '1 Example Street',
            'suburb' => 'Sydney',
            'state' => 'NSW',
            'postcode' => '2000',
            'country_code' => 'AU',
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/investments', [
                'property_id' => $property->id,
                'investment_type' => 'property',
                'acquisition_date' => '2025-07-01',
                'cost_base' => '800000.00',
                'ownership_percentage' => '50.0000',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.property_id', $property->id);

        $this->assertDatabaseHas('investments', [
            'user_id' => $user->id,
            'property_id' => $property->id,
            'investment_type' => 'property',
        ]);
    }

    public function test_user_cannot_link_another_users_property(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $property = Property::create([
            'user_id' => $otherUser->id,
            'reference_code' => 'OTHER-001',
            'address_line_1' => '1 Other Street',
            'suburb' => 'Sydney',
            'state' => 'NSW',
            'postcode' => '2000',
            'country_code' => 'AU',
        ]);

        $this->actingAs($user)
            ->postJson('/api/investments', [
                'property_id' => $property->id,
                'investment_type' => 'property',
                'acquisition_date' => '2025-07-01',
            ])
            ->assertStatus(500);

        $this->assertDatabaseMissing('investments', [
            'user_id' => $user->id,
            'property_id' => $property->id,
        ]);
    }

    public function test_investment_validation_rejects_invalid_ownership(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/investments', [
                'investment_type' => 'shares',
                'acquisition_date' => '2025-08-01',
                'ownership_percentage' => '100.0001',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'ownership_percentage',
            ]);
    }

    public function test_investment_validation_rejects_disposal_before_acquisition(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/investments', [
                'investment_type' => 'shares',
                'acquisition_date' => '2025-08-01',
                'disposal_date' => '2025-07-31',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'disposal_date',
            ]);
    }

    public function test_investment_requires_type_and_acquisition_date(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/investments', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'investment_type',
                'acquisition_date',
            ]);
    }

    public function test_unauthenticated_user_cannot_access_investments(): void
    {
        $this->postJson('/api/investments', [])
            ->assertUnauthorized();

        $this->getJson('/api/investments')
            ->assertUnauthorized();
    }

    public function test_user_can_only_see_their_own_investments(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/investments', [
                'investment_type' => 'shares',
                'acquisition_date' => '2025-08-01',
                'external_id' => 'OWN-001',
            ])
            ->assertCreated();

        $this->actingAs($otherUser)
            ->postJson('/api/investments', [
                'investment_type' => 'shares',
                'acquisition_date' => '2025-08-01',
                'external_id' => 'OTHER-001',
            ])
            ->assertCreated();

        $response = $this->actingAs($user)
            ->getJson('/api/investments')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame(
            'OWN-001',
            $response->json('data.0.external_id')
        );
    }

    public function test_user_can_retrieve_their_own_investment(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/investments', [
                'investment_type' => 'shares',
                'acquisition_date' => '2025-08-01',
                'quantity' => '100.00000000',
                'external_id' => 'INV-SHOW-001',
            ])
            ->assertCreated();

        $investmentId = $response->json('data.id');

        $this->actingAs($user)
            ->getJson("/api/investments/{$investmentId}")
            ->assertOk()
            ->assertJsonPath('data.id', $investmentId)
            ->assertJsonPath('data.external_id', 'INV-SHOW-001');
    }

    public function test_user_cannot_retrieve_another_users_investment(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $response = $this->actingAs($owner)
            ->postJson('/api/investments', [
                'investment_type' => 'shares',
                'acquisition_date' => '2025-08-01',
                'external_id' => 'OWNER-INV-001',
            ])
            ->assertCreated();

        $investmentId = $response->json('data.id');

        $this->actingAs($otherUser)
            ->getJson("/api/investments/{$investmentId}")
            ->assertNotFound();
    }
}
