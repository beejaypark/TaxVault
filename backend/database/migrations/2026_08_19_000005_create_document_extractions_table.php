<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_extractions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('document_id')->constrained('documents')->restrictOnDelete();
            $table->string('provider', 100);
            $table->string('model', 150)->nullable();
            $table->string('model_version', 100)->nullable();
            $table->string('extraction_version', 50);
            $table->string('status', 30);
            $table->uuid('correlation_id')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->jsonb('output')->nullable();
            $table->jsonb('quality_metadata')->nullable();
            $table->jsonb('error_metadata')->nullable();
            $table->timestampsTz();
            $table->index(['document_id', 'created_at']);
            $table->index('correlation_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_extractions');
    }
};
