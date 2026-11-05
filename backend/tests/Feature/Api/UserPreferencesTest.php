<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPreferencesTest extends TestCase
{
    use RefreshDatabase;

    private array $payload = [
        'notifyDocuments' => false,
        'notifyUncategorised' => true,
        'defaultFinancialYear' => 'all',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession(['_token' => 'preferences-test-token']);
        $this->withHeader('X-CSRF-TOKEN', 'preferences-test-token');
    }

    public function test_preferences_are_saved_and_returned_with_the_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/auth/preferences', $this->payload)
            ->assertOk()
            ->assertJsonPath('data.defaultFinancialYear', 'all');

        $this->actingAs($user)->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonPath('data.preferences.notifyDocuments', false);
    }

    public function test_preferences_are_private_to_each_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->putJson('/api/auth/preferences', $this->payload)->assertOk();

        $this->actingAs($other)->getJson('/api/auth/user')
            ->assertJsonPath('data.preferences', null);
    }

    public function test_invalid_preferences_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/auth/preferences', [
            ...$this->payload,
            'defaultFinancialYear' => 'bogus',
        ])->assertUnprocessable();
    }

    public function test_guests_cannot_save_preferences(): void
    {
        $this->putJson('/api/auth/preferences', $this->payload)->assertUnauthorized();
    }
}
