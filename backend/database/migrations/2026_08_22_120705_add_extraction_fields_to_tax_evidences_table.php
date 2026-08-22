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
            $table->foreignUuid('extraction_id')
                ->nullable()
                ->after('document_id')
                ->constrained('document_extractions')
                ->restrictOnDelete();

            $table->string('field_path', 500)
                ->nullable()
                ->after('source_id');

            $table->jsonb('extracted_value')
                ->nullable()
                ->after('field_path');

            $table->index([
                'extraction_id',
                'field_path',
            ]);
        });

        DB::statement("
            ALTER TABLE tax_evidences
            ADD CONSTRAINT tax_evidences_extraction_source_check
            CHECK (
                source_type <> 'document_extraction'
                OR (
                    extraction_id IS NOT NULL
                    AND field_path IS NOT NULL
                    AND extracted_value IS NOT NULL
                )
            )
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE tax_evidences
            DROP CONSTRAINT IF EXISTS tax_evidences_extraction_source_check
        ");

        Schema::table('tax_evidences', function (Blueprint $table) {
            $table->dropForeign(['extraction_id']);

            $table->dropIndex([
                'extraction_id',
                'field_path',
            ]);

            $table->dropColumn([
                'extraction_id',
                'field_path',
                'extracted_value',
            ]);
        });
    }
};
