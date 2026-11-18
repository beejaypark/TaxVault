<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_connections', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider', 50);
            $table->string('external_account_id', 255);
            $table->string('display_name', 255)->nullable();
            $table->string('status', 30)->default('connected');
            $table->jsonb('metadata')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestampTz('token_expires_at')->nullable();
            $table->text('scopes')->nullable();
            $table->timestampTz('connected_at')->useCurrent();
            $table->timestampTz('last_synced_at')->nullable();
            $table->timestampTz('last_error_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampsTz();

            $table->unique(['user_id', 'provider', 'external_account_id'], 'storage_connections_identity_unique');
            $table->index(['user_id', 'provider', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_connections');
    }
};
