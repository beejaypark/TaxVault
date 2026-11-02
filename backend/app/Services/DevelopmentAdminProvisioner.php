<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DevelopmentAdminProvisioner
{
    public function provision(string $login, string $password): ?bool
    {
        $login = strtolower(trim($login));

        if (
            strlen($login) < 3
            || strlen($login) > 100
            || ! preg_match('/^[a-z0-9._-]+$/', $login)
            || strlen($password) < 12
        ) {
            return null;
        }

        return DB::transaction(function () use ($login, $password): bool {
            if (
                DB::table('development_admin_credentials')
                    ->where('login_name', $login)
                    ->exists()
            ) {
                return false;
            }

            $user = DB::table('users')
                ->where('identity_provider', 'local-dev')
                ->where('provider_subject', $login)
                ->first();

            if ($user && ($user->status !== 'active' || $user->deactivated_at !== null)) {
                return null;
            }

            $userId = $user?->id ?? (string) Str::uuid();
            $now = now();

            if (! $user) {
                DB::table('users')->insert([
                    'id' => $userId,
                    'identity_provider' => 'local-dev',
                    'provider_subject' => $login,
                    'email' => null,
                    'display_name' => 'Administrator',
                    'status' => 'active',
                    'deactivated_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('development_admin_credentials')->insert([
                'user_id' => $userId,
                'login_name' => $login,
                'password_hash' => Hash::make($password),
                'enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return true;
        });
    }
}
