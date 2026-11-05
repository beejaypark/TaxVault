<?php

namespace Tests\Feature\Api\Exports;

use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Transactions\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TransactionCsvExportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_download_financial_year_transactions_as_csv(): void
    {
        [$user, $financialYear] = $this->createContext();
        $this->createTransaction($user, $financialYear, '2026-09-10', 'expense', '548.80', 'Council rates');
        $this->createTransaction($user, $financialYear, '2026-08-01', 'income', '1200.00', '=HYPERLINK("http://evil")');

        $otherYear = FinancialYear::query()->create([
            'user_id' => $user->id,
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
        ]);
        $this->createTransaction($user, $otherYear, '2026-01-01', 'expense', '9.99', 'Other year');

        $response = $this->actingAs($user)
            ->get('/api/exports/transactions.csv?financial_year_id='.$financialYear->id)
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $lines = array_values(array_filter(explode("\n", trim(substr($csv, 3)))));

        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertSame('Date,Type,Description,Amount,Currency,Document,"Payment status","Payment method",Source,"Tax return item",Property', trim($lines[0]));
        $this->assertCount(3, $lines);
        $this->assertStringStartsWith('2026-08-01,income,', $lines[1]);
        $this->assertStringContainsString("'=HYPERLINK", $lines[1]);
        $this->assertStringContainsString('2026-09-10,expense,"Council rates",548.80', $lines[2]);
        $this->assertStringNotContainsString('Other year', $csv);
    }

    public function test_user_cannot_export_another_users_financial_year(): void
    {
        [, $financialYear] = $this->createContext();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->getJson('/api/exports/transactions.csv?financial_year_id='.$financialYear->id)
            ->assertNotFound();
    }

    public function test_csv_export_requires_authentication_and_a_financial_year(): void
    {
        [$user, $financialYear] = $this->createContext();

        $this->getJson('/api/exports/transactions.csv?financial_year_id='.$financialYear->id)
            ->assertUnauthorized();

        $this->actingAs($user)
            ->getJson('/api/exports/transactions.csv')
            ->assertUnprocessable();
    }

    public function test_document_content_can_be_opened_inline_for_printing(): void
    {
        Storage::fake('documents');
        [$user, $financialYear] = $this->createContext();
        $type = DocumentType::query()->create([
            'code' => 'CSV_TEST',
            'name' => 'CSV test',
            'status' => 'active',
            'sort_order' => 1,
        ]);
        Storage::disk('documents')->put('print/receipt.pdf', '%PDF-1.4');
        $document = Document::query()->create([
            'user_id' => $user->id,
            'financial_year_id' => $financialYear->id,
            'document_type_id' => $type->id,
            'storage_disk' => 'documents',
            'object_key' => 'print/receipt.pdf',
            'content_sha256' => hash('sha256', 'print'),
            'original_filename' => 'receipt.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 8,
            'uploaded_at' => now(),
            'status' => 'review_required',
        ]);

        $inline = $this->actingAs($user)->get('/api/documents/'.$document->id.'/content?inline=1');
        $inline->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringStartsWith('inline', $inline->headers->get('Content-Disposition'));

        $download = $this->actingAs($user)->get('/api/documents/'.$document->id.'/content');
        $this->assertStringStartsWith('attachment', $download->headers->get('Content-Disposition'));
    }

    private function createContext(): array
    {
        $user = User::factory()->create();
        $financialYear = FinancialYear::query()->create([
            'user_id' => $user->id,
            'year_code' => '2026-27',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'status' => 'active',
        ]);

        return [$user, $financialYear];
    }

    private function createTransaction(
        User $user,
        FinancialYear $financialYear,
        string $date,
        string $direction,
        string $amount,
        string $description,
    ): Transaction {
        return Transaction::query()->create([
            'user_id' => $user->id,
            'financial_year_id' => $financialYear->id,
            'transaction_date' => $date,
            'description' => $description,
            'direction' => $direction,
            'amount' => $amount,
            'currency' => 'AUD',
            'source_system' => 'manual',
        ]);
    }
}
