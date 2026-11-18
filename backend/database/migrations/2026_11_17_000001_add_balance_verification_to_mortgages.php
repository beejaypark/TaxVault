<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mortgages', function (Blueprint $table): void {
            $table->decimal('verified_balance', 14, 2)->nullable()->after('maturity_date');
            $table->date('verified_balance_date')->nullable()->after('verified_balance');
        });
    }

    public function down(): void
    {
        Schema::table('mortgages', function (Blueprint $table): void {
            $table->dropColumn(['verified_balance', 'verified_balance_date']);
        });
    }
};
