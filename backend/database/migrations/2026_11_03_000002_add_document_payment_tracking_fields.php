<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->string('payment_status', 20)->default('unconfirmed');
            $table->date('payment_date')->nullable();
            $table->string('payment_method', 30)->nullable();
            $table->decimal('payment_amount', 18, 2)->nullable();
        });

        DB::statement("
            ALTER TABLE documents
            ADD CONSTRAINT documents_payment_details_check CHECK (
                (
                    payment_status = 'unconfirmed'
                    AND payment_date IS NULL
                    AND payment_method IS NULL
                    AND payment_amount IS NULL
                )
                OR (
                    payment_status = 'paid'
                    AND payment_date IS NOT NULL
                    AND payment_method IS NOT NULL
                    AND payment_amount > 0
                )
            )
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE documents DROP CONSTRAINT documents_payment_details_check');

        Schema::table('documents', function (Blueprint $table): void {
            $table->dropColumn([
                'payment_status',
                'payment_date',
                'payment_method',
                'payment_amount',
            ]);
        });
    }
};
