<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_news', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('source', 80);
            $table->string('external_id', 500);
            $table->string('title', 500);
            $table->text('summary')->nullable();
            $table->text('url');
            $table->string('category', 40)->default('general');
            $table->string('importance', 20)->default('info');
            $table->string('status', 30)->default('guidance');
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('effective_at')->nullable();
            $table->timestampsTz();

            $table->unique(['source', 'external_id']);
            $table->index(['importance', 'published_at']);
            $table->index(['category', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_news');
    }
};
