<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_categories', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('code', 100);
            $table->string('name', 255);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 20)->default('active');
            $table->string('taxonomy_version', 30);
            $table->timestampsTz();

            $table->unique(['taxonomy_version', 'code']);
        });

        Schema::create('tax_subcategories', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tax_category_id')->constrained('tax_categories')->restrictOnDelete();
            $table->string('code', 100);
            $table->string('name', 255);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 20)->default('active');
            $table->string('taxonomy_version', 30);
            $table->timestampsTz();

            $table->unique(['taxonomy_version', 'code']);
            $table->index(['tax_category_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_subcategories');
        Schema::dropIfExists('tax_categories');
    }
};
