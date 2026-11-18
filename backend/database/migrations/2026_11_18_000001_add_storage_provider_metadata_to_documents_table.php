<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            // `taxvault` keeps the current upload behaviour as the default provider.
            $table->string('storage_provider', 50)->default('taxvault')->after('document_type_id');
            $table->string('external_account_id', 255)->nullable()->after('storage_provider');
            $table->string('external_drive_id', 255)->nullable()->after('external_account_id');
            $table->string('external_item_id', 1024)->nullable()->after('external_drive_id');
            $table->string('external_url', 2048)->nullable()->after('external_item_id');
            $table->string('external_version', 512)->nullable()->after('external_url');
            $table->string('sync_status', 30)->default('local')->after('external_version');
            $table->timestampTz('last_synced_at')->nullable()->after('sync_status');

            $table->index(['user_id', 'storage_provider', 'sync_status']);
            $table->index(['storage_provider', 'external_account_id', 'external_item_id'], 'documents_external_identity_index');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropIndex('documents_external_identity_index');
            $table->dropIndex(['user_id', 'storage_provider', 'sync_status']);
            $table->dropColumn([
                'storage_provider',
                'external_account_id',
                'external_drive_id',
                'external_item_id',
                'external_url',
                'external_version',
                'sync_status',
                'last_synced_at',
            ]);
        });
    }
};
