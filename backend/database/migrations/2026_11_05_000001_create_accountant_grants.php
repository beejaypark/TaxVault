<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('owner')->after('status');
        });

        Schema::create('accountant_grants', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('accountant_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('firm')->nullable();
            $table->string('status', 20)->default('active');
            // null means every financial year is visible.
            $table->jsonb('financial_year_ids')->nullable();
            $table->jsonb('sections');
            $table->timestampTz('last_invited_at')->nullable();
            $table->timestampTz('last_pack_sent_at')->nullable();
            $table->timestampsTz();

            $table->unique(['owner_id', 'accountant_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accountant_grants');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
