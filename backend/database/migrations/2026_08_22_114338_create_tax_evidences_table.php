<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_evidences', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('gen_random_uuid()'));

            $table->foreignUuid('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignUuid('financial_year_id')
                ->constrained('financial_years')
                ->restrictOnDelete();

            $table->string('evidence_type', 50);

            $table->string('source_type', 50);

            $table->uuid('source_id')->nullable();

            $table->string('status', 30)
                ->default('active');

            $table->string('verification_status', 30)
                ->default('pending');

            $table->decimal('confidence', 5, 2)
                ->nullable();

            $table->timestampTz('verified_at')
                ->nullable();

            $table->timestampsTz();

            $table->index([
                'user_id',
                'financial_year_id',
            ]);

            $table->index([
                'source_type',
                'source_id',
            ]);

            $table->index('evidence_type');

            $table->index('verification_status');

        });

        DB::statement("
            ALTER TABLE tax_evidences
            ADD CONSTRAINT tax_evidences_confidence_check
            CHECK (confidence >= 0 AND confidence <= 100)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_evidences');
    }
};
