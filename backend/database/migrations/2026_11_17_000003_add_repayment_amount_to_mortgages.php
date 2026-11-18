<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mortgages', function (Blueprint $table): void {
            $table->decimal('repayment_amount', 14, 2)->nullable()->after('loan_amount');
        });
    }

    public function down(): void
    {
        Schema::table('mortgages', function (Blueprint $table): void {
            $table->dropColumn('repayment_amount');
        });
    }
};
