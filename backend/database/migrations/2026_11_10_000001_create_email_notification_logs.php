<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_notification_logs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 40);
            $table->string('dedupe_key', 120);
            $table->timestampTz('sent_at')->useCurrent();

            $table->unique(['user_id', 'kind', 'dedupe_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_notification_logs');
    }
};
