<?php

namespace App\Console\Commands;

use App\Services\DevelopmentAdminProvisioner;
use Illuminate\Console\Command;

class ProvisionDefaultDevelopmentAdmin extends Command
{
    protected $signature = 'taxvault:dev-admin:provision-default';

    protected $description = 'Provision the configured local development admin if needed';

    public function handle(DevelopmentAdminProvisioner $provisioner): int
    {
        if (! app()->environment('local')) {
            return self::SUCCESS;
        }

        if (! (bool) config('dev_admin.enabled', false)) {
            $this->warn('Development admin login is disabled; skipping default account provisioning.');

            return self::SUCCESS;
        }

        $login = (string) config('dev_admin.login');
        $password = (string) config('dev_admin.password');

        $result = $provisioner->provision($login, $password);

        if ($result === null) {
            $this->warn(
                'Default development admin was not created. Check DEV_ADMIN_LOGIN and DEV_ADMIN_PASSWORD; the password must be at least 12 characters.',
            );

            return self::SUCCESS;
        }

        $this->info($result
            ? 'Default development admin provisioned.'
            : 'Default development admin already exists; keeping its current credentials.');

        return self::SUCCESS;
    }
}
