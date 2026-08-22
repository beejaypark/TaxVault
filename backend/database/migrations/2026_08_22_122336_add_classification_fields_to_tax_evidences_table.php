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
            $table->foreignUuid('tax_category_id')
                ->nullable()
                ->after('extracted_value')
                ->constrained('tax_categories')
                ->restrictOnDelete();

            $table->foreignUuid('tax_subcategory_id')
                ->nullable()
                ->after('tax_category_id')
                ->constrained('tax_subcategories')
                ->restrictOnDelete();

            $table->text('classification_reason')
                ->nullable()
                ->after('tax_subcategory_id');

            $table->index([
                'tax_category_id',
                'tax_subcategory_id',
            ]);
        });

        DB::statement("
            ALTER TABLE tax_evidences
            ADD CONSTRAINT tax_evidences_classification_source_check
            CHECK (
                source_type <> 'classification'
                OR (
                    tax_category_id IS NOT NULL
                    AND classification_reason IS NOT NULL
                )
            )
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE tax_evidences
            DROP CONSTRAINT IF EXISTS tax_evidences_classification_source_check
        ");

        Schema::table('tax_evidences', function (Blueprint $table) {
            $table->dropForeign(['tax_category_id']);
            $table->dropForeign(['tax_subcategory_id']);

            $table->dropIndex([
                'tax_category_id',
                'tax_subcategory_id',
            ]);

            $table->dropColumn([
                'tax_category_id',
                'tax_subcategory_id',
                'classification_reason',
            ]);
        });
    }
};
