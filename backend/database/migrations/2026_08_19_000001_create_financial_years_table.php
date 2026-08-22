<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        Schema::create('financial_years', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->string('year_code', 7);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('active');
            $table->timestampsTz();

            $table->unique(['user_id', 'year_code']);
            $table->index(['user_id', 'start_date', 'end_date']);
        });

        DB::statement('ALTER TABLE financial_years ADD CONSTRAINT financial_years_dates_valid CHECK (start_date < end_date)');

        DB::statement("\n            ALTER TABLE financial_years\n            ADD CONSTRAINT financial_years_no_overlapping_active_periods\n            EXCLUDE USING gist (\n                user_id WITH =,\n                daterange(start_date, end_date + 1, '[)') WITH &&\n            ) WHERE (status = 'active')\n        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_years');
    }
};
