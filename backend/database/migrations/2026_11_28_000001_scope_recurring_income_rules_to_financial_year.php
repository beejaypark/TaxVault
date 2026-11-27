<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurring_income_rules', function (Blueprint $table): void {
            $table->foreignUuid('financial_year_id')
                ->nullable()
                ->after('user_id')
                ->constrained('financial_years')
                ->cascadeOnDelete();
            $table->index(['user_id', 'financial_year_id', 'active'], 'recurring_income_year_active_idx');
        });

        DB::table('recurring_income_rules')->orderBy('id')->each(function (object $rule): void {
            $financialYearId = DB::table('financial_years')
                ->where('user_id', $rule->user_id)
                ->whereDate('start_date', '<=', $rule->start_date)
                ->whereDate('end_date', '>=', $rule->start_date)
                ->value('id');

            if ($financialYearId !== null) {
                DB::table('recurring_income_rules')
                    ->where('id', $rule->id)
                    ->update(['financial_year_id' => $financialYearId]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('recurring_income_rules', function (Blueprint $table): void {
            $table->dropIndex('recurring_income_year_active_idx');
            $table->dropConstrainedForeignId('financial_year_id');
        });
    }
};
