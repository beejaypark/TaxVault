<?php

namespace Tests\Unit\Domain\Evidence\Models;

use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\Documents\Models\DocumentExtraction;
use App\Domain\Evidence\Models\TaxEvidence;
use App\Domain\Taxonomy\Models\TaxCategory;
use App\Domain\Taxonomy\Models\TaxSubcategory;
use App\Domain\FinancialYears\Models\FinancialYear;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_tax_evidence_uses_uuid_primary_key(): void
    {
        $evidence = new TaxEvidence();

        $this->assertFalse($evidence->incrementing);
        $this->assertSame('string', $evidence->getKeyType());
    }

    public function test_tax_evidence_has_expected_fillable_attributes(): void
    {
        $evidence = new TaxEvidence();

        $this->assertSame([
            'user_id',
            'financial_year_id',
            'document_id',
            'extraction_id',
            'evidence_type',
            'source_type',
            'source_id',
            'field_path',
            'extracted_value',
            'tax_category_id',
            'tax_subcategory_id',
            'classification_reason',
            'status',
            'verification_status',
            'confidence',
            'verified_at',
        ], $evidence->getFillable());
    }

    public function test_tax_evidence_casts_confidence_and_verified_at(): void
    {
        $evidence = new TaxEvidence();

        $casts = $evidence->getCasts();

        $this->assertSame('decimal:2', $casts['confidence']);
        $this->assertSame('datetime', $casts['verified_at']);
    }

    public function test_tax_evidence_can_belong_to_user_and_financial_year(): void
    {
        $user = User::factory()->create();

        $financialYear = $this->createFinancialYear($user);

        $evidence = TaxEvidence::create([
            'user_id' => $user->id,
            'financial_year_id' => $financialYear->id,
            'evidence_type' => 'source',
            'source_type' => 'transaction',
            'source_id' => null,
            'status' => 'active',
            'verification_status' => 'pending',
            'confidence' => 96.50,
        ]);

        $this->assertInstanceOf(User::class, $evidence->user);
        $this->assertInstanceOf(FinancialYear::class, $evidence->financialYear);

        $this->assertSame($user->id, $evidence->user->id);
        $this->assertSame($financialYear->id, $evidence->financialYear->id);
    }

    public function test_confidence_must_be_between_zero_and_one_hundred(): void
    {
        $user = User::factory()->create();

        $financialYear = $this->createFinancialYear($user);

        $this->expectException(QueryException::class);

        TaxEvidence::create([
            'user_id' => $user->id,
            'financial_year_id' => $financialYear->id,
            'evidence_type' => 'source',
            'source_type' => 'transaction',
            'source_id' => null,
            'status' => 'active',
            'verification_status' => 'pending',
            'confidence' => 100.01,
        ]);
    }

    public function test_tax_evidence_can_link_to_source_document(): void
    {
        $user = User::factory()->create();

        $financialYear = $this->createFinancialYear($user);

        $documentType = DocumentType::create([
            'code' => 'TEST-RECEIPT',
            'name' => 'Test Receipt',
            'description' => 'Test document type for evidence tests.',
            'classification_metadata' => [
                'test' => true,
            ],
            'status' => 'active',
            'sort_order' => 1,
            'version' => '1.0',
        ]);

        $document = Document::create([
            'user_id' => $user->id,
            'financial_year_id' => $financialYear->id,
            'document_type_id' => $documentType->id,
            'storage_disk' => 'local',
            'object_key' => 'evidence/test-document.pdf',
            'content_sha256' => hash('sha256', 'test-document'),
            'original_filename' => 'test-document.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1234,
            'status' => 'active',
        ]);

        $evidence = TaxEvidence::create([
            'user_id' => $user->id,
            'financial_year_id' => $financialYear->id,
            'document_id' => $document->id,
            'evidence_type' => 'source',
            'source_type' => 'document',
            'source_id' => $document->id,
            'status' => 'active',
            'verification_status' => 'pending',
            'confidence' => 99.00,
        ]);

        $this->assertInstanceOf(Document::class, $evidence->document);
        $this->assertSame($document->id, $evidence->document->id);

        $this->assertTrue(
            $document->evidences->contains('id', $evidence->id)
        );
    }

    public function test_document_source_requires_document_id(): void
    {
        $user = User::factory()->create();

        $financialYear = $this->createFinancialYear($user);

        $this->expectException(QueryException::class);

        TaxEvidence::create([
            'user_id' => $user->id,
            'financial_year_id' => $financialYear->id,
            'document_id' => null,
            'evidence_type' => 'source',
            'source_type' => 'document',
            'source_id' => null,
            'status' => 'active',
            'verification_status' => 'pending',
            'confidence' => 95.00,
        ]);
    }

    private function createFinancialYear(User $user): FinancialYear
    {
        $financialYear = new FinancialYear([
            'user_id' => $user->id,
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
        ]);

        $financialYear->save();

        return $financialYear;
    }

    public function test_tax_evidence_can_reference_extracted_fact(): void
    {
        $user = User::factory()->create();

        $financialYear = $this->createFinancialYear($user);

        $documentType = DocumentType::create([
            'code' => 'TEST-EXTRACTION',
            'name' => 'Test Extraction Document',
            'description' => 'Test document type.',
            'classification_metadata' => [
                'test' => true,
            ],
            'status' => 'active',
            'sort_order' => 1,
            'version' => '1.0',
        ]);

        $document = Document::create([
            'user_id' => $user->id,
            'financial_year_id' => $financialYear->id,
            'document_type_id' => $documentType->id,
            'storage_disk' => 'local',
            'object_key' => 'evidence/extraction-test.pdf',
            'content_sha256' => hash('sha256', 'extraction-test'),
            'original_filename' => 'extraction-test.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 2048,
            'status' => 'active',
        ]);

        $extraction = DocumentExtraction::create([
            'document_id' => $document->id,
            'provider' => 'test-provider',
            'model' => 'test-model',
            'model_version' => '1.0',
            'extraction_version' => '1.0',
            'status' => 'completed',
            'output' => [
                'supplier.name' => 'ACME Pty Ltd',
                'total.amount' => 123.45,
            ],
            'quality_metadata' => [
                'confidence' => 98.50,
            ],
        ]);

        $evidence = TaxEvidence::create([
            'user_id' => $user->id,
            'financial_year_id' => $financialYear->id,
            'document_id' => $document->id,
            'extraction_id' => $extraction->id,
            'evidence_type' => 'extracted_fact',
            'source_type' => 'document_extraction',
            'source_id' => $extraction->id,
            'field_path' => 'total.amount',
            'extracted_value' => [
                'value' => 123.45,
                'type' => 'decimal',
            ],
            'status' => 'active',
            'verification_status' => 'pending',
            'confidence' => 98.50,
        ]);

        $this->assertInstanceOf(
            DocumentExtraction::class,
            $evidence->extraction
        );

        $this->assertSame(
            $extraction->id,
            $evidence->extraction->id
        );

        $this->assertSame(
            'total.amount',
            $evidence->field_path
        );

        $this->assertSame(
            123.45,
            $evidence->extracted_value['value']
        );

        $this->assertTrue(
            $extraction->evidences->contains('id', $evidence->id)
        );

        $this->assertTrue(
            $document->extractions->contains('id', $extraction->id)
        );
    }

    public function test_document_extraction_source_requires_extraction_metadata(): void
    {
        $user = User::factory()->create();

        $financialYear = $this->createFinancialYear($user);

        $this->expectException(\Illuminate\Database\QueryException::class);

        TaxEvidence::create([
            'user_id' => $user->id,
            'financial_year_id' => $financialYear->id,
            'evidence_type' => 'extracted_fact',
            'source_type' => 'document_extraction',
            'source_id' => null,
            'extraction_id' => null,
            'field_path' => null,
            'extracted_value' => null,
            'status' => 'active',
            'verification_status' => 'pending',
            'confidence' => 90.00,
        ]);
    }

    public function test_tax_evidence_can_record_classification(): void
    {
        $user = User::factory()->create();

        $financialYear = $this->createFinancialYear($user);

        $category = TaxCategory::create([
            'code' => 'WORK',
            'name' => 'Work Related',
            'sort_order' => 1,
            'status' => 'active',
            'taxonomy_version' => '2026.1',
        ]);

        $subcategory = TaxSubcategory::create([
            'tax_category_id' => $category->id,
            'code' => 'EQUIPMENT',
            'name' => 'Work Equipment',
            'sort_order' => 1,
            'status' => 'active',
            'taxonomy_version' => '2026.1',
        ]);

        $evidence = TaxEvidence::create([
            'user_id' => $user->id,
            'financial_year_id' => $financialYear->id,
            'evidence_type' => 'classification',
            'source_type' => 'classification',
            'source_id' => null,
            'tax_category_id' => $category->id,
            'tax_subcategory_id' => $subcategory->id,
            'classification_reason' =>
                'The extracted transaction description indicates work equipment.',
            'status' => 'active',
            'verification_status' => 'pending',
            'confidence' => 94.50,
        ]);

        $this->assertInstanceOf(
            TaxCategory::class,
            $evidence->taxCategory
        );

        $this->assertInstanceOf(
            TaxSubcategory::class,
            $evidence->taxSubcategory
        );

        $this->assertSame(
            $category->id,
            $evidence->taxCategory->id
        );

        $this->assertSame(
            $subcategory->id,
            $evidence->taxSubcategory->id
        );

        $this->assertSame(
            'The extracted transaction description indicates work equipment.',
            $evidence->classification_reason
        );

        $this->assertTrue(
            $category->evidences->contains('id', $evidence->id)
        );

        $this->assertTrue(
            $subcategory->evidences->contains('id', $evidence->id)
        );
    }

    public function test_classification_evidence_requires_tax_category_and_reason(): void
    {
        $user = User::factory()->create();

        $financialYear = $this->createFinancialYear($user);

        $this->expectException(\Illuminate\Database\QueryException::class);

        TaxEvidence::create([
            'user_id' => $user->id,
            'financial_year_id' => $financialYear->id,
            'evidence_type' => 'classification',
            'source_type' => 'classification',
            'source_id' => null,
            'tax_category_id' => null,
            'tax_subcategory_id' => null,
            'classification_reason' => null,
            'status' => 'active',
            'verification_status' => 'pending',
            'confidence' => 90.00,
        ]);
    }
}
