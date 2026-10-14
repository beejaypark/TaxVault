<?php

namespace Tests\Feature\Api;

use App\Models\DevelopmentAdminCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DevelopmentAdminAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['dev_admin.enabled' => true]);

        $this->withServerVariables([
            'HTTP_HOST' => 'localhost:8000',
            'HTTP_ORIGIN' => 'http://localhost:4200',
            'HTTP_REFERER' => 'http://localhost:4200/',
        ]);

        $this->withHeaders([
            'Origin' => 'http://localhost:4200',
            'Referer' => 'http://localhost:4200/',
        ]);
    }

    public function test_development_login_is_disabled_when_feature_flag_is_off(): void
    {
        config(['dev_admin.enabled' => false]);

        $this->postJson('/api/auth/login', [
            'login' => 'devadmin',
            'password' => 'a-long-test-password',
        ])->assertNotFound();
    }

    public function test_active_development_admin_can_log_in(): void
    {
        $user = User::factory()->create([
            'identity_provider' => 'local-dev',
            'provider_subject' => 'devadmin',
            'status' => 'active',
            'deactivated_at' => null,
        ]);

        DevelopmentAdminCredential::query()->create([
            'user_id' => $user->id,
            'login_name' => 'devadmin',
            'password_hash' => Hash::make('a-long-test-password'),
            'enabled' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'login' => 'devadmin',
            'password' => 'a-long-test-password',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email);

        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_invalid_development_admin_password_is_rejected(): void
    {
        $user = User::factory()->create([
            'identity_provider' => 'local-dev',
            'provider_subject' => 'devadmin',
        ]);

        DevelopmentAdminCredential::query()->create([
            'user_id' => $user->id,
            'login_name' => 'devadmin',
            'password_hash' => Hash::make('correct-test-password'),
            'enabled' => true,
        ]);

        $this->postJson('/api/auth/login', [
            'login' => 'devadmin',
            'password' => 'wrong-test-password',
        ])->assertUnprocessable();

        $this->assertGuest('web');
    }

    public function test_disabled_development_admin_credential_cannot_log_in(): void
    {
        $user = User::factory()->create([
            'identity_provider' => 'local-dev',
            'provider_subject' => 'devadmin',
        ]);

        DevelopmentAdminCredential::query()->create([
            'user_id' => $user->id,
            'login_name' => 'devadmin',
            'password_hash' => Hash::make('a-long-test-password'),
            'enabled' => false,
        ]);

        $this->postJson('/api/auth/login', [
            'login' => 'devadmin',
            'password' => 'a-long-test-password',
        ])->assertUnprocessable();

        $this->assertGuest('web');
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $user = User::factory()->create([
            'identity_provider' => 'local-dev',
            'provider_subject' => 'devadmin',
            'status' => 'inactive',
            'deactivated_at' => null,
        ]);

        DevelopmentAdminCredential::query()->create([
            'user_id' => $user->id,
            'login_name' => 'devadmin',
            'password_hash' => Hash::make('a-long-test-password'),
            'enabled' => true,
        ]);

        $this->postJson('/api/auth/login', [
            'login' => 'devadmin',
            'password' => 'a-long-test-password',
        ])->assertUnprocessable();

        $this->assertGuest('web');
    }
}
