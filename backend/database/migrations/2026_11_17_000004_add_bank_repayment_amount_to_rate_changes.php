<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mortgage_rate_changes', function (Blueprint $table): void {
            $table->decimal('bank_repayment_amount', 14, 2)->nullable()->after('rate');
        });

        DB::statement('UPDATE mortgage_rate_changes SET bank_repayment_amount = mortgages.repayment_amount FROM mortgages WHERE mortgages.id = mortgage_rate_changes.mortgage_id AND mortgage_rate_changes.effective_date = COALESCE(mortgages.start_date, mortgages.created_at::date)');
    }

    public function down(): void
    {
        Schema::table('mortgage_rate_changes', function (Blueprint $table): void {
            $table->dropColumn('bank_repayment_amount');
        });
    }
};
