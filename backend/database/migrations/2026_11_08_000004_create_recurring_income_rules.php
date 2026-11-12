<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_income_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('description', 1000);
            $table->decimal('amount', 18, 2);
            $table->unsignedTinyInteger('day_of_month')->default(1);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('ato_category', 40)->nullable();
            $table->boolean('active')->default(true);
            $table->timestampsTz();
            $table->index(['user_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_income_rules');
    }
};
