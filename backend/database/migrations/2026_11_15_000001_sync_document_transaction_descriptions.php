<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('documents')
            ->whereNotNull('description')
            ->where('description', '!=', '')
            ->orderBy('id')
            ->get(['id', 'description'])
            ->each(function (object $document): void {
                DB::table('transactions')
                    ->where('document_id', $document->id)
                    ->whereIn('source_system', ['document_payment_confirmation', 'document_review'])
                    ->update(['description' => $document->description]);
            });
    }

    public function down(): void
    {
        // This is a corrective data migration; the original descriptions are not recoverable.
    }
};
