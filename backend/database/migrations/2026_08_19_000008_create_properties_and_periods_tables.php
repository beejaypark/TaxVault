<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->string('reference_code', 100)->nullable();
            $table->string('address_line_1', 255)->nullable();
            $table->string('address_line_2', 255)->nullable();
            $table->string('suburb', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postcode', 20)->nullable();
            $table->string('country_code', 2)->default('AU');
            $table->jsonb('location_metadata')->nullable();
            $table->timestampsTz();
            $table->index(['user_id', 'reference_code']);
        });
        Schema::create('property_periods', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('property_id')->constrained('properties')->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end')->nullable();
            $table->string('use_type', 50);
            $table->decimal('ownership_percentage', 7, 4)->nullable();
            $table->jsonb('provenance')->nullable();
            $table->timestampsTz();
            $table->index(['property_id', 'period_start', 'period_end']);
        });
        DB::statement('ALTER TABLE property_periods ADD CONSTRAINT property_periods_dates_valid CHECK (period_end IS NULL OR period_end >= period_start)');
        DB::statement('ALTER TABLE property_periods ADD CONSTRAINT property_periods_ownership_percentage_valid CHECK (ownership_percentage IS NULL OR (ownership_percentage >= 0 AND ownership_percentage <= 100))');
        DB::statement("ALTER TABLE property_periods ADD CONSTRAINT property_periods_no_overlap EXCLUDE USING gist (property_id WITH =, daterange(period_start, COALESCE(period_end + 1, 'infinity'::date), '[)') WITH &&)");
    }

    public function down(): void
    {
        Schema::dropIfExists('property_periods');
        Schema::dropIfExists('properties');
    }
};
