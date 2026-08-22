<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tax_evidences', function (Blueprint $table) {
            $table->foreignUuid('document_id')
                ->nullable()
                ->after('financial_year_id')
                ->constrained('documents')
                ->restrictOnDelete();

            $table->index([
                'financial_year_id',
                'document_id',
            ]);
        });

        DB::statement("
            ALTER TABLE tax_evidences
            ADD CONSTRAINT tax_evidences_document_source_check
            CHECK (
                source_type <> 'document'
                OR document_id IS NOT NULL
            )
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE tax_evidences
            DROP CONSTRAINT IF EXISTS tax_evidences_document_source_check
        ");

        Schema::table('tax_evidences', function (Blueprint $table) {
            $table->dropForeign(['document_id']);
            $table->dropIndex([
                'financial_year_id',
                'document_id',
            ]);
            $table->dropColumn('document_id');
        });
    }
};
