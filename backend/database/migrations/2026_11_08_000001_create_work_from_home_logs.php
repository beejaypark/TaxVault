<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_from_home_logs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->date('work_date');
            $table->decimal('hours', 4, 2);
            $table->string('note', 255)->nullable();
            $table->timestampsTz();

            $table->unique(['user_id', 'work_date']);
        });

        Schema::table('financial_years', function (Blueprint $table) {
            $table->decimal('wfh_rate', 5, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('financial_years', function (Blueprint $table) {
            $table->dropColumn('wfh_rate');
        });

        Schema::dropIfExists('work_from_home_logs');
    }
};
