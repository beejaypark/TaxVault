<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('documents')
                ->where('payment_status', 'paid')
                ->whereNotNull('payment_date')
                ->whereNotNull('payment_amount')
                ->whereNotNull('financial_year_id')
                ->whereNotExists(function ($query): void {
                    $query->select(DB::raw(1))
                        ->from('transactions')
                        ->whereColumn('transactions.document_id', 'documents.id');
                })
                ->orderBy('id')
                ->get([
                    'id',
                    'user_id',
                    'financial_year_id',
                    'original_filename',
                    'description',
                    'payment_date',
                    'payment_amount',
                ])
                ->each(function (object $document): void {
                    $now = now();
                    $transactionId = DB::table('transactions')->insertGetId([
                        'user_id' => $document->user_id,
                        'financial_year_id' => $document->financial_year_id,
                        'document_id' => $document->id,
                        'transaction_date' => $document->payment_date,
                        'description' => $document->description ?: 'Payment for '.$document->original_filename,
                        'direction' => 'expense',
                        'amount' => $document->payment_amount,
                        'currency' => 'AUD',
                        'source_system' => 'document_payment_confirmation_backfill',
                        'provenance' => json_encode([
                            'backfill' => 'paid_document_without_transaction',
                            'document_id' => $document->id,
                        ], JSON_THROW_ON_ERROR),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ], 'id');

                    DB::table('expenses')->insert([
                        'transaction_id' => $transactionId,
                        'user_id' => $document->user_id,
                        'financial_year_id' => $document->financial_year_id,
                        'amount' => $document->payment_amount,
                        'source_system' => 'document_payment_confirmation_backfill',
                        'metadata' => json_encode([
                            'document_id' => $document->id,
                        ], JSON_THROW_ON_ERROR),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                });
        });
    }

    public function down(): void
    {
        DB::table('expenses')
            ->where('source_system', 'document_payment_confirmation_backfill')
            ->delete();

        DB::table('transactions')
            ->where('source_system', 'document_payment_confirmation_backfill')
            ->delete();
    }
};
