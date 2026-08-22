<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('document_id')->nullable()->constrained('documents')->restrictOnDelete();
            $table->foreignUuid('document_extraction_id')->nullable()->constrained('document_extractions')->restrictOnDelete();
            $table->foreignUuid('transaction_id')->nullable()->constrained('transactions')->restrictOnDelete();
            $table->string('review_type', 50);
            $table->string('status', 30);
            $table->string('decision', 50)->nullable();
            $table->text('reviewer_notes')->nullable();
            $table->uuid('supersedes_review_id')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
            $table->index(['reviewer_id', 'created_at']);
            $table->index(['document_id', 'created_at']);
            $table->index(['transaction_id', 'created_at']);
        });
        DB::statement('ALTER TABLE reviews ADD CONSTRAINT reviews_supersedes_review_id_foreign FOREIGN KEY (supersedes_review_id) REFERENCES reviews (id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE reviews ADD CONSTRAINT reviews_has_target CHECK ((document_id IS NOT NULL)::integer + (document_extraction_id IS NOT NULL)::integer + (transaction_id IS NOT NULL)::integer >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
