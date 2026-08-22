<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investments', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('property_id')->nullable()->constrained('properties')->restrictOnDelete();
            $table->string('investment_type', 50);
            $table->date('acquisition_date');
            $table->date('disposal_date')->nullable();
            $table->decimal('quantity', 20, 8)->nullable();
            $table->decimal('ownership_percentage', 7, 4)->nullable();
            $table->decimal('cost_base', 18, 2)->nullable();
            $table->decimal('incidental_costs', 18, 2)->nullable();
            $table->decimal('proceeds', 18, 2)->nullable();
            $table->string('source_system', 100)->nullable();
            $table->string('external_id', 255)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->index(['user_id', 'acquisition_date']);
            $table->index('property_id');
            $table->index(['source_system', 'external_id']);
        });
        DB::statement('ALTER TABLE investments ADD CONSTRAINT investments_dates_valid CHECK (disposal_date IS NULL OR disposal_date >= acquisition_date)');
        DB::statement('ALTER TABLE investments ADD CONSTRAINT investments_ownership_percentage_valid CHECK (ownership_percentage IS NULL OR (ownership_percentage >= 0 AND ownership_percentage <= 100))');
    }

    public function down(): void
    {
        Schema::dropIfExists('investments');
    }
};
