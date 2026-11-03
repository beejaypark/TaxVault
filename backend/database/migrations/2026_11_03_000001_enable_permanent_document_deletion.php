<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_extractions', function (Blueprint $table) {
            $table->dropForeign(['document_id']);
            $table->uuid('document_id')->nullable()->change();
            $table->uuid('source_document_id')->nullable();
            $table->uuid('source_financial_year_id')->nullable();
            $table->foreign('document_id')
                ->references('id')
                ->on('documents')
                ->nullOnDelete();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['document_id']);
            $table->uuid('source_document_id')->nullable();
            $table->foreign('document_id')
                ->references('id')
                ->on('documents')
                ->nullOnDelete();
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign(['document_id']);
            $table->uuid('source_document_id')->nullable();
            $table->uuid('source_financial_year_id')->nullable();
            $table->foreign('document_id')
                ->references('id')
                ->on('documents')
                ->nullOnDelete();
        });

        DB::statement('ALTER TABLE reviews DROP CONSTRAINT reviews_has_target');
        DB::statement('
            ALTER TABLE reviews
            ADD CONSTRAINT reviews_has_target CHECK (
                (document_id IS NOT NULL)::integer
                + (source_document_id IS NOT NULL)::integer
                + (document_extraction_id IS NOT NULL)::integer
                + (transaction_id IS NOT NULL)::integer >= 1
            )
        ');

        Schema::table('financial_record_documents', function (Blueprint $table) {
            $table->dropForeign(['document_id']);
            $table->uuid('document_id')->nullable()->change();
            $table->uuid('source_document_id')->nullable();
            $table->foreign('document_id')
                ->references('id')
                ->on('documents')
                ->nullOnDelete();
        });

        Schema::table('tax_evidences', function (Blueprint $table) {
            $table->dropForeign(['document_id']);
            $table->foreign('document_id')
                ->references('id')
                ->on('documents')
                ->nullOnDelete();
        });

        DB::statement('ALTER TABLE tax_evidences DROP CONSTRAINT tax_evidences_document_source_check');
        DB::statement("
            ALTER TABLE tax_evidences
            ADD CONSTRAINT tax_evidences_document_source_check
            CHECK (
                source_type <> 'document'
                OR document_id IS NOT NULL
                OR source_id IS NOT NULL
            )
        ");
    }

    public function down(): void
    {
        DB::statement('
            ALTER TABLE tax_evidences
            DROP CONSTRAINT tax_evidences_document_source_check
        ');
        DB::statement("
            ALTER TABLE tax_evidences
            ADD CONSTRAINT tax_evidences_document_source_check
            CHECK (source_type <> 'document' OR document_id IS NOT NULL)
        ");

        Schema::table('tax_evidences', function (Blueprint $table) {
            $table->dropForeign(['document_id']);
            $table->foreign('document_id')
                ->references('id')
                ->on('documents')
                ->restrictOnDelete();
        });

        Schema::table('financial_record_documents', function (Blueprint $table) {
            $table->dropForeign(['document_id']);
            $table->foreign('document_id')
                ->references('id')
                ->on('documents')
                ->restrictOnDelete();
            $table->dropColumn('source_document_id');
            $table->uuid('document_id')->nullable(false)->change();
        });

        DB::statement('ALTER TABLE reviews DROP CONSTRAINT reviews_has_target');
        DB::statement('
            ALTER TABLE reviews
            ADD CONSTRAINT reviews_has_target CHECK (
                (document_id IS NOT NULL)::integer
                + (document_extraction_id IS NOT NULL)::integer
                + (transaction_id IS NOT NULL)::integer >= 1
            )
        ');

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign(['document_id']);
            $table->foreign('document_id')
                ->references('id')
                ->on('documents')
                ->restrictOnDelete();
            $table->dropColumn([
                'source_document_id',
                'source_financial_year_id',
            ]);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['document_id']);
            $table->foreign('document_id')
                ->references('id')
                ->on('documents')
                ->restrictOnDelete();
            $table->dropColumn('source_document_id');
        });

        Schema::table('document_extractions', function (Blueprint $table) {
            $table->dropForeign(['document_id']);
            $table->foreign('document_id')
                ->references('id')
                ->on('documents')
                ->restrictOnDelete();
            $table->dropColumn([
                'source_document_id',
                'source_financial_year_id',
            ]);
            $table->uuid('document_id')->nullable(false)->change();
        });
    }
};
