<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 120);
            $table->text('message');
            $table->string('theme', 20)->default('blue');
            $table->string('icon', 20)->default('megaphone');
            $table->string('font_family', 20)->default('system');
            $table->boolean('is_bold')->default(false);
            $table->boolean('is_dismissible')->default(true);
            $table->string('action_label', 60)->nullable();
            $table->string('action_url', 2048)->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->timestampsTz();
            $table->index(['is_published', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
