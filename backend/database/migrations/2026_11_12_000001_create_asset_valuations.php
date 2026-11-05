<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->decimal('current_value', 14, 2)->nullable();
            $table->date('current_value_date')->nullable();
        });

        Schema::create('asset_valuations', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('property_id')->nullable()->constrained('properties')->cascadeOnDelete();
            $table->foreignUuid('investment_id')->nullable()->constrained('investments')->cascadeOnDelete();
            $table->decimal('value', 14, 2);
            $table->date('as_of');
            $table->timestampsTz();

            $table->index(['user_id', 'as_of']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_valuations');
        Schema::table('investments', function (Blueprint $table) {
            $table->dropColumn(['current_value', 'current_value_date']);
        });
    }
};
