<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_provider_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider', 50);
            $table->text('client_id');
            $table->text('client_secret');
            $table->string('tenant', 255)->default('common');
            $table->timestampsTz();

            $table->unique(['user_id', 'provider'], 'storage_provider_settings_user_provider_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_provider_settings');
    }
};
