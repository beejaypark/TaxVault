<?php

use App\Services\DevelopmentAdminProvisioner;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $login = strtolower(trim((string) config('dev_admin.login')));
        $password = (string) config('dev_admin.password');

        if ($login === '' || $password === '') {
            return;
        }

        app(DevelopmentAdminProvisioner::class)->provision($login, $password);
    }

    public function down(): void
    {
        // Keep the provisioned account when rolling back schema changes.
    }
};
