<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_record_documents', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('document_id')->constrained('documents')->restrictOnDelete();
            $table->foreignUuid('transaction_id')->constrained('transactions')->restrictOnDelete();
            $table->string('relation_type', 50);
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->unique(['document_id', 'transaction_id', 'relation_type']);
            $table->index(['transaction_id', 'relation_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_record_documents');
    }
};
