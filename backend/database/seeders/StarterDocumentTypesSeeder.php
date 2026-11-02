<?php

namespace Database\Seeders;

use App\Domain\Documents\Models\DocumentType;
use Illuminate\Database\Seeder;

class StarterDocumentTypesSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'code' => 'receipt',
                'name' => 'Receipt',
                'description' => 'Receipt or proof of purchase.',
                'sort_order' => 10,
            ],
            [
                'code' => 'tax_invoice',
                'name' => 'Tax invoice',
                'description' => 'Invoice issued for goods or services.',
                'sort_order' => 20,
            ],
            [
                'code' => 'bank_statement',
                'name' => 'Bank statement',
                'description' => 'Bank or financial account statement.',
                'sort_order' => 30,
            ],
            [
                'code' => 'payment_summary',
                'name' => 'Payment summary',
                'description' => 'Income or payment summary from a payer.',
                'sort_order' => 40,
            ],
            [
                'code' => 'tax_notice',
                'name' => 'Tax notice',
                'description' => 'Tax assessment or other tax authority notice.',
                'sort_order' => 50,
            ],
            [
                'code' => 'other',
                'name' => 'Other tax document',
                'description' => 'Tax-related document not covered by another type.',
                'sort_order' => 60,
            ],
        ];

        foreach ($types as $type) {
            DocumentType::query()->firstOrCreate(
                ['code' => $type['code']],
                [
                    ...$type,
                    'status' => 'active',
                    'version' => 'starter-1.0',
                ],
            );
        }
    }
}
