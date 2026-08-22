<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('financial_year_id')->constrained('financial_years')->restrictOnDelete();
            $table->foreignUuid('document_id')->nullable()->constrained('documents')->restrictOnDelete();
            $table->date('transaction_date');
            $table->date('settlement_date')->nullable();
            $table->string('description', 1000)->nullable();
            $table->string('direction', 20);
            $table->decimal('amount', 18, 2);
            $table->char('currency', 3)->default('AUD');
            $table->string('source_system', 100)->nullable();
            $table->string('external_transaction_id', 255)->nullable();
            $table->jsonb('provenance')->nullable();
            $table->uuid('tax_category_id')->nullable();
            $table->timestampsTz();
            $table->index(['user_id', 'financial_year_id', 'transaction_date']);
            $table->index('document_id');
            $table->index(['source_system', 'external_transaction_id']);
        });

        DB::statement('ALTER TABLE transactions ADD CONSTRAINT transactions_amount_non_negative CHECK (amount >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
