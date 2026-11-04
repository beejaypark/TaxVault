<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->decimal('current_value', 14, 2)->nullable();
            $table->date('current_value_date')->nullable();
        });

        Schema::create('mortgages', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('property_id')->constrained('properties')->restrictOnDelete();
            $table->string('bank_name', 100);
            $table->string('loan_type', 20)->default('variable');
            $table->decimal('loan_amount', 14, 2);
            $table->decimal('interest_rate', 6, 3)->nullable();
            $table->date('start_date')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'property_id']);
        });

        Schema::create('mortgage_interest_entries', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('mortgage_id')->constrained('mortgages')->restrictOnDelete();
            $table->date('month');
            $table->decimal('interest_amount', 14, 2);
            $table->timestampsTz();

            $table->unique(['mortgage_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mortgage_interest_entries');
        Schema::dropIfExists('mortgages');

        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['current_value', 'current_value_date']);
        });
    }
};
