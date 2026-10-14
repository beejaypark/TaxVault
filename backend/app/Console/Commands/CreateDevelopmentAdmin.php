<?php

namespace App\Console\Commands;

use App\Models\DevelopmentAdminCredential;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CreateDevelopmentAdmin extends Command
{
    protected $signature = 'taxvault:dev-admin:create';

    protected $description = 'Create a local-only TaxVault development admin account';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('This command is only available in local/testing environments.');
            return self::FAILURE;
        }

        if (! (bool) config('dev_admin.enabled', false)) {
            $this->error('Set DEV_ADMIN_LOGIN_ENABLED=true in the local .env first.');
            return self::FAILURE;
        }

        $login = strtolower(trim((string) $this->ask('Admin login name')));
        $email = trim((string) $this->ask('Admin email'));
        $displayName = trim((string) $this->ask('Display name'));

        if (
            strlen($login) < 3
            || strlen($login) > 100
            || ! preg_match('/^[a-z0-9._-]+$/', $login)
        ) {
            $this->error('Login must be 3-100 characters: letters, numbers, dot, underscore or hyphen.');
            return self::FAILURE;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Enter a valid email address.');
            return self::FAILURE;
        }

        if ($displayName === '') {
            $this->error('Display name cannot be empty.');
            return self::FAILURE;
        }

        if (DevelopmentAdminCredential::query()->where('login_name', $login)->exists()) {
            $this->error('That login name already exists.');
            return self::FAILURE;
        }

        $password = (string) $this->secret('Password (minimum 12 characters)');
        $confirmation = (string) $this->secret('Confirm password');

        if (strlen($password) < 12 || ! hash_equals($password, $confirmation)) {
            $this->error('Password confirmation failed or the password is too short.');
            return self::FAILURE;
        }

        DB::transaction(function () use ($login, $email, $displayName, $password): void {
            $user = User::query()->create([
                'identity_provider' => 'local-dev',
                'provider_subject' => $login,
                'email' => $email,
                'display_name' => $displayName,
                'status' => 'active',
            ]);

            DevelopmentAdminCredential::query()->create([
                'user_id' => $user->id,
                'login_name' => $login,
                'password_hash' => Hash::make($password),
                'enabled' => true,
            ]);
        });

        $this->info('Development admin created. The password was stored as a hash.');
        return self::SUCCESS;
    }
}