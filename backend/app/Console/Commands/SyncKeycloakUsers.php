<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncKeycloakUsers extends Command
{
    protected $signature = 'keycloak:sync-users';
    protected $description = 'Deactivate local customers that no longer exist in Keycloak';

    public function handle(): int
    {
        $baseUrl = rtrim((string) config('services.keycloak.base_url'), '/');
        $adminUser = (string) config('services.keycloak.admin_user');
        $adminPassword = (string) config('services.keycloak.admin_password');
        $realm = (string) config('services.keycloak.realms', 'taxvault');

        if ($baseUrl === '' || $adminUser === '' || $adminPassword === '') {
            $this->warn('Keycloak administrator credentials are not configured.');
            return self::SUCCESS;
        }

        try {
            $token = Http::asForm()->post($baseUrl.'/realms/master/protocol/openid-connect/token', [
                'client_id' => 'admin-cli',
                'username' => $adminUser,
                'password' => $adminPassword,
                'grant_type' => 'password',
            ])->throw()->json('access_token');

            if (! is_string($token) || $token === '') {
                throw new \RuntimeException('Keycloak did not return an administrator token.');
            }

            $subjects = [];
            $first = 0;
            do {
                $users = Http::withToken($token)
                    ->get($baseUrl.'/admin/realms/'.rawurlencode($realm).'/users', [
                        'first' => $first,
                        'max' => 100,
                    ])->throw()->json();
                $users = is_array($users) ? $users : [];
                foreach ($users as $keycloakUser) {
                    if (is_string($keycloakUser['id'] ?? null)) {
                        $subjects[] = $keycloakUser['id'];
                    }
                }
                $first += count($users);
            } while (count($users) === 100);

            $deactivated = 0;
            User::query()
                ->where('identity_provider', 'keycloak')
                ->whereNotNull('provider_subject')
                ->get()
                ->each(function (User $user) use ($subjects, &$deactivated): void {
                    if ($user->isPlatformAdmin() || in_array($user->provider_subject, $subjects, true)) {
                        return;
                    }

                    if ($user->status === 'active' || $user->deactivated_at === null) {
                        $user->status = 'inactive';
                        $user->deactivated_at = now();
                        $user->save();
                        $deactivated++;
                    }
                });

            $this->info("Keycloak sync complete. Deactivated {$deactivated} customer(s).");
            return self::SUCCESS;
        } catch (\Throwable $exception) {
            Log::error('Keycloak user synchronization failed.', ['error' => $exception->getMessage()]);
            $this->error('Keycloak synchronization failed: '.$exception->getMessage());
            return self::FAILURE;
        }
    }
}
