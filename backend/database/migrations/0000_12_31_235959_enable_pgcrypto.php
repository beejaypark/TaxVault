<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException('TaxVault requires PostgreSQL for UUID support.');
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pgcrypto');
    }

    public function down(): void
    {
        // pgcrypto may be shared by other schemas; do not remove it on rollback.
    }
};
