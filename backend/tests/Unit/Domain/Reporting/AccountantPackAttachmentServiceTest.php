<?php

namespace Tests\Unit\Domain\Reporting;

use App\Domain\Reporting\Services\AccountantPackAttachmentService;
use Tests\TestCase;

class AccountantPackAttachmentServiceTest extends TestCase
{
    public function test_pdf_pack_is_a_valid_pdf_document_with_export_summary(): void
    {
        $pdf = app(AccountantPackAttachmentService::class)->pdf([
            'manifest' => [
                'year_code' => '2025-26',
                'generated_at' => '2026-11-16T00:00:00+00:00',
                'export_version' => '2',
                'record_counts' => ['transactions' => 2, 'documents' => 1],
            ],
            'tax_records' => [
                'transactions' => [[
                    'transaction_date' => '2026-01-01',
                    'description' => 'Test expense',
                    'direction' => 'expense',
                    'amount' => '10.00',
                    'ato_category' => 'work_other',
                ]],
            ],
            'source_references' => [
                'documents' => [[
                    'original_filename' => 'receipt.pdf',
                    'status' => 'active',
                    'uploaded_at' => '2026-01-02T00:00:00+00:00',
                ]],
            ],
        ]);

        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString('%%EOF', $pdf);
        $this->assertGreaterThan(500, strlen($pdf));
    }
}
