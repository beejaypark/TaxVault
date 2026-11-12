<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_deductions', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignUuid('financial_year_id')->constrained('financial_years')->cascadeOnDelete();
            $table->foreignUuid('source_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->string('deduction_type', 40);
            $table->decimal('amount', 14, 2);
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['property_id', 'financial_year_id', 'deduction_type']);
            $table->index(['user_id', 'financial_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_deductions');
    }
};
