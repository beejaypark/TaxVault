<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('storage_connections', 'access_token')) {
            return;
        }

        Schema::table('storage_connections', function (Blueprint $table): void {
            $table->text('access_token')->nullable()->after('metadata');
            $table->text('refresh_token')->nullable()->after('access_token');
            $table->timestampTz('token_expires_at')->nullable()->after('refresh_token');
            $table->text('scopes')->nullable()->after('token_expires_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('storage_connections', 'access_token')) {
            return;
        }

        Schema::table('storage_connections', function (Blueprint $table): void {
            $table->dropColumn(['access_token', 'refresh_token', 'token_expires_at', 'scopes']);
        });
    }
};
