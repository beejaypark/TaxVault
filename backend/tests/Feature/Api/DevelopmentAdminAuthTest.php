<?php

namespace Tests\Feature\Api;

use App\Models\DevelopmentAdminCredential;
use App\Models\User;
use App\Services\DevelopmentAdminProvisioner;
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

        $csrfToken = 'development-admin-test-token';
        $this->withSession(['_token' => $csrfToken]);
        $this->withHeader('X-CSRF-TOKEN', $csrfToken);
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

        $this->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);

        $this->withHeader('X-CSRF-TOKEN', $this->app['session.store']->token());

        $this->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out.');

        $this->assertGuest('web');
        $this->getJson('/api/auth/user')->assertUnauthorized();
    }

    public function test_default_admin_from_environment_can_log_in(): void
    {
        config([
            'dev_admin.login' => 'provisioned-admin-test',
            'dev_admin.password' => 'test-only-admin-password',
        ]);
        $this->assertTrue(app(DevelopmentAdminProvisioner::class)->provision(
            'provisioned-admin-test',
            'test-only-admin-password',
        ));

        $login = 'provisioned-admin-test';
        $password = 'test-only-admin-password';
        $credential = DevelopmentAdminCredential::query()
            ->where('login_name', $login)
            ->firstOrFail();

        $this->assertTrue(Hash::check($password, $credential->password_hash));
        $this->assertSame('Administrator', $credential->user->display_name);

        $this->postJson('/api/auth/login', [
            'login' => $login,
            'password' => $password,
        ])->assertOk()
            ->assertJsonPath('data.id', $credential->user_id);

        $this->assertAuthenticatedAs($credential->user, 'web');
    }

    public function test_default_admin_provisioning_skips_invalid_environment_without_failing(): void
    {
        config([
            'dev_admin.login' => '',
            'dev_admin.password' => '',
        ]);

        $this->assertNull(app(DevelopmentAdminProvisioner::class)->provision('', ''));
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
