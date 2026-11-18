<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mortgages', function (Blueprint $table): void {
            $table->string('repayment_frequency', 20)->default('monthly')->after('loan_type');
        });
    }

    public function down(): void
    {
        Schema::table('mortgages', function (Blueprint $table): void {
            $table->dropColumn('repayment_frequency');
        });
    }
};
