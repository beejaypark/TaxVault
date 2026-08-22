<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('financial_year_id')->constrained('financial_years')->restrictOnDelete();
            $table->foreignUuid('document_type_id')->constrained('document_types')->restrictOnDelete();
            $table->string('storage_disk', 50);
            $table->string('object_key', 1024);
            $table->char('content_sha256', 64);
            $table->string('original_filename', 512)->nullable();
            $table->string('mime_type', 255)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestampTz('captured_at')->nullable();
            $table->timestampTz('uploaded_at')->useCurrent();
            $table->jsonb('provenance')->nullable();
            $table->string('status', 30)->default('active');
            $table->timestampsTz();

            $table->unique(['storage_disk', 'object_key']);
            $table->index('content_sha256');
            $table->index(['user_id', 'financial_year_id']);
            $table->index('document_type_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
