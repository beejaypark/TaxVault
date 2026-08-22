<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_year_export_archives', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->foreignUuid('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignUuid('financial_year_id')
                ->constrained('financial_years')
                ->restrictOnDelete();

            $table->string('export_version', 20);
            $table->string('sha256', 64);
            $table->timestampTz('generated_at');

            $table->jsonb('payload');

            $table->timestampsTz();

            $table->index(['user_id', 'financial_year_id']);
            $table->index(['user_id', 'created_at']);
            $table->unique(['financial_year_id', 'sha256']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_year_export_archives');
    }
};
