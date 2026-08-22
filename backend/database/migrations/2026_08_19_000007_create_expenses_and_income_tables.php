<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['expenses', 'income'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
                $table->foreignUuid('transaction_id')->constrained('transactions')->restrictOnDelete();
                $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
                $table->foreignUuid('financial_year_id')->constrained('financial_years')->restrictOnDelete();
                $table->uuid('tax_category_id')->nullable();
                $table->uuid('tax_subcategory_id')->nullable();
                $table->decimal('amount', 18, 2);
                $table->string('source_system', 100)->nullable();
                $table->string('external_id', 255)->nullable();
                $table->jsonb('metadata')->nullable();
                $table->timestampsTz();
                $table->index(['user_id', 'financial_year_id']);
                $table->index(['source_system', 'external_id']);
            });

            DB::statement("ALTER TABLE {$name} ADD CONSTRAINT {$name}_amount_non_negative CHECK (amount >= 0)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('income');
        Schema::dropIfExists('expenses');
    }
};
