<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('stripe_customer_id')->nullable()->unique();
            $table->string('stripe_subscription_id')->nullable()->index();
            $table->string('stripe_subscription_status', 30)->nullable()->index();
            $table->timestampTz('trial_ends_at')->nullable();
            $table->timestampTz('subscription_ends_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'stripe_customer_id',
                'stripe_subscription_id',
                'stripe_subscription_status',
                'trial_ends_at',
                'subscription_ends_at',
            ]);
        });
    }
};
