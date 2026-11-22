<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_notification_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient');
            $table->string('notification_type');
            $table->string('status', 20);
            $table->text('error')->nullable();
            $table->timestampTz('created_at');
            $table->index(['notification_type', 'created_at']);
        });
    }

    public function down(): void { Schema::dropIfExists('admin_notification_logs'); }
};
