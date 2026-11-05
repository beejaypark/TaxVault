<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mortgage_rate_changes', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('mortgage_id')->constrained('mortgages')->cascadeOnDelete();
            $table->date('effective_date');
            $table->decimal('rate', 6, 3);
            $table->timestampsTz();
            $table->unique(['mortgage_id', 'effective_date']);
        });

        // Seed one history point from each mortgage's current rate so existing loans start with a baseline.
        DB::statement("INSERT INTO mortgage_rate_changes (mortgage_id, effective_date, rate, created_at, updated_at)
            SELECT id, COALESCE(start_date, created_at::date), interest_rate, now(), now()
            FROM mortgages WHERE interest_rate IS NOT NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('mortgage_rate_changes');
    }
};
