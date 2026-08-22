<?php

namespace Tests\Feature\Database;

use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Taxonomy\Models\TaxCategory;
use App\Domain\Taxonomy\Models\TaxSubcategory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class Db001To008DatabaseTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | DB001 - Users / UUID baseline
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function db001_user_has_uuid_primary_key_configuration(): void
    {
        $user = User::factory()->create([
            'identity_provider' => 'db001-test',
            'provider_subject' => 'db001-'.Str::uuid(),
            'email' => 'db001-'.Str::uuid().'@example.com',
            'display_name' => 'DB001 Test User',
            'status' => 'active',
        ]);

        $this->assertNotEmpty($user->id);
        $this->assertIsString($user->getKey());
        $this->assertSame('string', $user->getKeyType());
        $this->assertFalse($user->getIncrementing());

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => $user->email,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DB002 - Financial Years
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function db002_financial_year_accepts_canonical_australian_period(): void
    {
        $user = $this->createUser('db002-canonical');

        $financialYear = FinancialYear::create([
            'user_id' => $user->id,
            'year_code' => '2026-27',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('financial_years', [
            'id' => $financialYear->id,
            'user_id' => $user->id,
            'year_code' => '2026-27',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'status' => 'active',
        ]);
    }

    #[Test]
    public function db002_financial_year_rejects_non_canonical_period(): void
    {
        $this->createUser('db002-invalid');

        $this->expectException(\InvalidArgumentException::class);

        FinancialYear::assertCanonicalAustralianPeriod(
            '2025-26',
            CarbonImmutable::parse('2025-07-01'),
            CarbonImmutable::parse('2026-07-01'),
        );
    }

    #[Test]
    public function db002_active_financial_years_cannot_overlap_for_same_user(): void
    {
        $user = $this->createUser('db002-overlap');

        FinancialYear::create([
            'user_id' => $user->id,
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
        ]);

        /*
         * Australian financial years are canonical 1 July -> 30 June
         * periods. Therefore, two canonical periods cannot partially
         * overlap; an overlap means attempting to insert the same period.
         *
         * The database constraint must reject this duplicate/overlapping
         * active financial year.
         */
        $this->expectException(QueryException::class);

        FinancialYear::create([
            'user_id' => $user->id,
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
        ]);
    }

    #[Test]
    public function db002_financial_years_are_scoped_to_user(): void
    {
        $user = $this->createUser('db002-owner');
        $otherUser = $this->createUser('db002-other');

        $userYear = FinancialYear::create([
            'user_id' => $user->id,
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
        ]);

        FinancialYear::create([
            'user_id' => $otherUser->id,
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
        ]);

        $this->assertCount(1, $user->financialYears);

        $this->assertSame(
            $userYear->id,
            $user->financialYears->first()->id
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DB003 - Tax Categories / Subcategories
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function db003_tax_category_and_subcategory_can_be_created_and_related(): void
    {
        $category = TaxCategory::create([
            'code' => 'DB003_TEST',
            'name' => 'DB003 Test Category',
            'sort_order' => 1,
            'status' => 'active',
            'taxonomy_version' => '1.0',
        ]);

        $subcategory = TaxSubcategory::create([
            'tax_category_id' => $category->id,
            'code' => 'DB003_TEST_SUB',
            'name' => 'DB003 Test Subcategory',
            'sort_order' => 1,
            'status' => 'active',
            'taxonomy_version' => '1.0',
        ]);

        $this->assertNotEmpty($category->id);
        $this->assertNotEmpty($subcategory->id);

        $this->assertTrue(
            $category->subcategories()
                ->whereKey($subcategory->id)
                ->exists()
        );

        $this->assertSame(
            $category->id,
            $subcategory->category->id
        );
    }

    #[Test]
    public function db003_category_code_can_repeat_across_taxonomy_versions(): void
    {
        TaxCategory::create([
            'code' => 'DB003_VERSIONED',
            'name' => 'Version 1',
            'sort_order' => 1,
            'status' => 'active',
            'taxonomy_version' => '1.0',
        ]);

        $versionTwo = TaxCategory::create([
            'code' => 'DB003_VERSIONED',
            'name' => 'Version 2',
            'sort_order' => 1,
            'status' => 'active',
            'taxonomy_version' => '2.0',
        ]);

        $this->assertDatabaseHas('tax_categories', [
            'code' => 'DB003_VERSIONED',
            'taxonomy_version' => '2.0',
        ]);

        $this->assertNotEmpty($versionTwo->id);
    }

    #[Test]
    public function db003_duplicate_category_code_within_same_version_is_rejected(): void
    {
        TaxCategory::create([
            'code' => 'DB003_DUPLICATE',
            'name' => 'Original',
            'sort_order' => 1,
            'status' => 'active',
            'taxonomy_version' => '9.9',
        ]);

        $this->expectException(QueryException::class);

        TaxCategory::create([
            'code' => 'DB003_DUPLICATE',
            'name' => 'Duplicate',
            'sort_order' => 2,
            'status' => 'active',
            'taxonomy_version' => '9.9',
        ]);
    }

    #[Test]
    public function db003_duplicate_subcategory_code_within_same_version_is_rejected(): void
    {
        $category = TaxCategory::create([
            'code' => 'DB003_SUB_DUP_CATEGORY',
            'name' => 'Category',
            'sort_order' => 1,
            'status' => 'active',
            'taxonomy_version' => '9.9',
        ]);

        TaxSubcategory::create([
            'tax_category_id' => $category->id,
            'code' => 'DB003_SUB_DUPLICATE',
            'name' => 'Original',
            'sort_order' => 1,
            'status' => 'active',
            'taxonomy_version' => '9.9',
        ]);

        $this->expectException(QueryException::class);

        TaxSubcategory::create([
            'tax_category_id' => $category->id,
            'code' => 'DB003_SUB_DUPLICATE',
            'name' => 'Duplicate',
            'sort_order' => 2,
            'status' => 'active',
            'taxonomy_version' => '9.9',
        ]);
    }

    #[Test]
    public function db003_category_cannot_be_deleted_when_subcategories_exist(): void
    {
        $category = TaxCategory::create([
            'code' => 'DB003_RESTRICT',
            'name' => 'Restrict Delete Category',
            'sort_order' => 1,
            'status' => 'active',
            'taxonomy_version' => '9.9',
        ]);

        TaxSubcategory::create([
            'tax_category_id' => $category->id,
            'code' => 'DB003_RESTRICT_SUB',
            'name' => 'Subcategory',
            'sort_order' => 1,
            'status' => 'active',
            'taxonomy_version' => '9.9',
        ]);

        $this->expectException(QueryException::class);

        $category->delete();
    }

    /*
    |--------------------------------------------------------------------------
    | DB004 - Document Types
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function db004_document_type_supports_json_metadata_and_uuid(): void
    {
        $documentType = DocumentType::create([
            'code' => 'DB004_VERIFY',
            'name' => 'DB004 Verification Document',
            'description' => 'Verification document type',
            'classification_metadata' => [
                'source' => 'test',
                'document_family' => 'verification',
                'supports_ocr' => true,
            ],
            'status' => 'active',
            'sort_order' => 10,
            'version' => '1.0',
        ]);

        $this->assertNotEmpty($documentType->id);
        $this->assertIsString($documentType->getKey());
        $this->assertSame('string', $documentType->getKeyType());
        $this->assertFalse($documentType->getIncrementing());

        $reloaded = DocumentType::findOrFail($documentType->id);

        /*
         * Do not compare the complete array with assertSame().
         * PostgreSQL JSON decoding may preserve a different key order.
         * The semantic values are what this test needs to protect.
         */
        $this->assertIsArray($reloaded->classification_metadata);

        $this->assertSame(
            'test',
            $reloaded->classification_metadata['source']
        );

        $this->assertSame(
            'verification',
            $reloaded->classification_metadata['document_family']
        );

        $this->assertTrue(
            $reloaded->classification_metadata['supports_ocr']
        );
    }

    #[Test]
    public function db004_document_type_code_is_unique(): void
    {
        DocumentType::create([
            'code' => 'DB004_DUPLICATE',
            'name' => 'Original',
            'classification_metadata' => ['test' => true],
            'status' => 'active',
            'sort_order' => 1,
            'version' => '1.0',
        ]);

        $this->expectException(QueryException::class);

        DocumentType::create([
            'code' => 'DB004_DUPLICATE',
            'name' => 'Duplicate',
            'classification_metadata' => ['duplicate' => true],
            'status' => 'active',
            'sort_order' => 2,
            'version' => '1.0',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DB005 - Documents
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function db005_document_can_be_created_with_evidence_metadata(): void
    {
        [$user, $financialYear, $documentType] = $this->createDocumentContext(
            'db005-create'
        );

        $document = Document::create([
            'user_id' => $user->id,
            'financial_year_id' => $financialYear->id,
            'document_type_id' => $documentType->id,
            'storage_disk' => 'local',
            'object_key' => 'db005/test-document.pdf',
            'content_sha256' => hash('sha256', 'DB005 test document'),
            'original_filename' => 'db005-test.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 12345,
            'provenance' => [
                'source' => 'test',
                'verified' => true,
            ],
            'status' => 'active',
        ]);

        $this->assertNotEmpty($document->id);

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'user_id' => $user->id,
            'financial_year_id' => $financialYear->id,
            'document_type_id' => $documentType->id,
            'storage_disk' => 'local',
            'object_key' => 'db005/test-document.pdf',
            'status' => 'active',
        ]);

        $this->assertSame(
            [
                'source' => 'test',
                'verified' => true,
            ],
            Document::findOrFail($document->id)->provenance
        );
    }

    #[Test]
    public function db005_document_object_key_is_unique_per_storage_disk(): void
    {
        [$user, $financialYear, $documentType] = $this->createDocumentContext(
            'db005-unique'
        );

        $base = [
            'user_id' => $user->id,
            'financial_year_id' => $financialYear->id,
            'document_type_id' => $documentType->id,
            'storage_disk' => 'local',
            'object_key' => 'db005/duplicate.pdf',
            'content_sha256' => hash('sha256', 'first'),
            'status' => 'active',
        ];

        Document::create($base);

        $this->expectException(QueryException::class);

        Document::create([
            ...$base,
            'content_sha256' => hash('sha256', 'second'),
        ]);
    }

    #[Test]
    public function db005_document_evidence_metadata_is_immutable(): void
    {
        [$user, $financialYear, $documentType] = $this->createDocumentContext(
            'db005-immutable'
        );

        $document = Document::create([
            'user_id' => $user->id,
            'financial_year_id' => $financialYear->id,
            'document_type_id' => $documentType->id,
            'storage_disk' => 'local',
            'object_key' => 'db005/immutable.pdf',
            'content_sha256' => hash('sha256', 'immutable'),
            'original_filename' => 'original.pdf',
            'mime_type' => 'application/pdf',
            'status' => 'active',
        ]);

        $document->original_filename = 'changed.pdf';

        $this->expectException(LogicException::class);

        $document->save();
    }

    #[Test]
    public function db005_document_status_can_be_updated(): void
    {
        [$user, $financialYear, $documentType] = $this->createDocumentContext(
            'db005-status'
        );

        $document = Document::create([
            'user_id' => $user->id,
            'financial_year_id' => $financialYear->id,
            'document_type_id' => $documentType->id,
            'storage_disk' => 'local',
            'object_key' => 'db005/status.pdf',
            'content_sha256' => hash('sha256', 'status'),
            'status' => 'active',
        ]);

        $document->status = 'archived';
        $document->save();

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'status' => 'archived',
        ]);
    }

    #[Test]
    public function db005_document_cannot_be_deleted(): void
    {
        [$user, $financialYear, $documentType] = $this->createDocumentContext(
            'db005-delete'
        );

        $document = Document::create([
            'user_id' => $user->id,
            'financial_year_id' => $financialYear->id,
            'document_type_id' => $documentType->id,
            'storage_disk' => 'local',
            'object_key' => 'db005/delete.pdf',
            'content_sha256' => hash('sha256', 'delete'),
            'status' => 'active',
        ]);

        $this->expectException(LogicException::class);

        $document->delete();
    }

    /*
    |--------------------------------------------------------------------------
    | DB006 - Taxonomy verification
    |--------------------------------------------------------------------------
    |
    | DB006 was the manual verification performed after DB003.
    | It is kept here as an explicit regression test so the behavior
    | remains protected independently of the original migration.
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function db006_taxonomy_models_use_uuid_keys(): void
    {
        $category = TaxCategory::create([
            'code' => 'DB006_UUID',
            'name' => 'DB006 UUID Category',
            'sort_order' => 1,
            'status' => 'active',
            'taxonomy_version' => '1.0',
        ]);

        $subcategory = TaxSubcategory::create([
            'tax_category_id' => $category->id,
            'code' => 'DB006_UUID_SUB',
            'name' => 'DB006 UUID Subcategory',
            'sort_order' => 1,
            'status' => 'active',
            'taxonomy_version' => '1.0',
        ]);

        $this->assertIsString($category->getKey());
        $this->assertFalse($category->getIncrementing());

        $this->assertIsString($subcategory->getKey());
        $this->assertFalse($subcategory->getIncrementing());
    }

    #[Test]
    public function db006_taxonomy_relationships_are_bidirectional(): void
    {
        $category = TaxCategory::create([
            'code' => 'DB006_REL',
            'name' => 'DB006 Relationship Category',
            'sort_order' => 1,
            'status' => 'active',
            'taxonomy_version' => '1.0',
        ]);

        $subcategory = TaxSubcategory::create([
            'tax_category_id' => $category->id,
            'code' => 'DB006_REL_SUB',
            'name' => 'DB006 Relationship Subcategory',
            'sort_order' => 1,
            'status' => 'active',
            'taxonomy_version' => '1.0',
        ]);

        $this->assertTrue(
            $category->subcategories()
                ->whereKey($subcategory->id)
                ->exists()
        );

        $this->assertSame(
            $category->id,
            $subcategory->category->id
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DB007 - Document Types verification
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function db007_document_type_json_metadata_round_trips_correctly(): void
    {
        $documentType = DocumentType::create([
            'code' => 'DB007_JSON',
            'name' => 'DB007 JSON Document',
            'description' => 'JSON verification',
            'classification_metadata' => [
                'source' => 'test',
                'document_family' => 'verification',
                'supports_ocr' => true,
            ],
            'status' => 'active',
            'sort_order' => 10,
            'version' => '1.0',
        ]);

        $reloaded = DocumentType::findOrFail($documentType->id);

        $this->assertIsArray($reloaded->classification_metadata);

        $this->assertTrue(
            $reloaded->classification_metadata['supports_ocr']
        );

        $this->assertSame(
            'verification',
            $reloaded->classification_metadata['document_family']
        );
    }

    #[Test]
    public function db007_document_type_code_cannot_be_duplicated(): void
    {
        DocumentType::create([
            'code' => 'DB007_UNIQUE',
            'name' => 'DB007 Original',
            'classification_metadata' => ['source' => 'test'],
            'status' => 'active',
            'sort_order' => 1,
            'version' => '1.0',
        ]);

        $this->expectException(QueryException::class);

        DocumentType::create([
            'code' => 'DB007_UNIQUE',
            'name' => 'DB007 Duplicate',
            'classification_metadata' => ['source' => 'duplicate'],
            'status' => 'active',
            'sort_order' => 2,
            'version' => '1.0',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DB008 - Documents verification
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function db008_document_relationships_are_resolved_correctly(): void
    {
        [$user, $financialYear, $documentType] = $this->createDocumentContext(
            'db008-rel'
        );

        $document = Document::create([
            'user_id' => $user->id,
            'financial_year_id' => $financialYear->id,
            'document_type_id' => $documentType->id,
            'storage_disk' => 'local',
            'object_key' => 'db008/relationships.pdf',
            'content_sha256' => hash('sha256', 'DB008 relationship'),
            'status' => 'active',
        ]);

        $this->assertSame(
            $user->id,
            $document->user->id
        );

        $this->assertSame(
            $financialYear->id,
            $document->financialYear->id
        );

        $this->assertSame(
            $documentType->id,
            $document->documentType->id
        );
    }

    #[Test]
    public function db008_document_evidence_remains_immutable_but_status_is_mutable(): void
    {
        [$user, $financialYear, $documentType] = $this->createDocumentContext(
            'db008-lifecycle'
        );

        $document = Document::create([
            'user_id' => $user->id,
            'financial_year_id' => $financialYear->id,
            'document_type_id' => $documentType->id,
            'storage_disk' => 'local',
            'object_key' => 'db008/lifecycle.pdf',
            'content_sha256' => hash('sha256', 'DB008 lifecycle'),
            'original_filename' => 'evidence.pdf',
            'mime_type' => 'application/pdf',
            'status' => 'active',
        ]);

        $document->status = 'archived';
        $document->save();

        $this->assertSame(
            'archived',
            $document->fresh()->status
        );

        $document = $document->fresh();

        $document->mime_type = 'text/plain';

        $this->expectException(LogicException::class);

        $document->save();
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function createUser(string $prefix): User
    {
        $uuid = (string) Str::uuid();

        return User::factory()->create([
            'identity_provider' => 'test',
            'provider_subject' => "{$prefix}-{$uuid}",
            'email' => "{$prefix}-{$uuid}@example.com",
            'display_name' => strtoupper($prefix),
            'status' => 'active',
        ]);
    }

    /**
     * @return array{
     *     0: User,
     *     1: FinancialYear,
     *     2: DocumentType
     * }
     */
    private function createDocumentContext(string $prefix): array
    {
        $user = $this->createUser($prefix);

        $financialYear = FinancialYear::create([
            'user_id' => $user->id,
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
        ]);

        $documentType = DocumentType::create([
            'code' => strtoupper($prefix).'_TYPE',
            'name' => strtoupper($prefix).' Document Type',
            'description' => 'Database test document type',
            'classification_metadata' => [
                'source' => 'automated-test',
            ],
            'status' => 'active',
            'sort_order' => 1,
            'version' => '1.0',
        ]);

        return [
            $user,
            $financialYear,
            $documentType,
        ];
    }
}
