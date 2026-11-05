<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('ato_category', 40)->nullable();
            $table->foreignUuid('property_id')->nullable()->constrained('properties')->nullOnDelete();

            $table->index(['property_id', 'ato_category']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('property_id');
            $table->dropColumn('ato_category');
        });
    }
};
