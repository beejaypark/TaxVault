<?php

namespace Tests\Feature\Database;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class Db009To016DatabaseTest extends TestCase
{
    use RefreshDatabase;

    private string $userId;

    private string $financialYearId;

    private string $documentTypeId;

    private string $documentId;

    private string $transactionId;

    private string $propertyId;

    private string $propertyPeriodId;

    private string $reviewId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userId = (string) Str::uuid();
        $this->financialYearId = (string) Str::uuid();
        $this->documentTypeId = (string) Str::uuid();
        $this->documentId = (string) Str::uuid();

        DB::table('users')->insert([
            'id' => $this->userId,
            'identity_provider' => 'test',
            'provider_subject' => 'db009-016-test-subject',
            'email' => 'db009-016-test@example.com',
            'display_name' => 'DB009-016 Test User',
            'status' => 'active',
        ]);

        DB::table('financial_years')->insert([
            'id' => $this->financialYearId,
            'user_id' => $this->userId,
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
        ]);

        DB::table('document_types')->insert([
            'id' => $this->documentTypeId,
            'code' => 'DB009_016_TEST',
            'name' => 'DB009-016 Test Document',
            'description' => 'Database verification document type',
            'classification_metadata' => json_encode([
                'source' => 'automated-test',
            ]),
            'status' => 'active',
            'sort_order' => 1,
            'version' => '1.0',
        ]);

        DB::table('documents')->insert([
            'id' => $this->documentId,
            'user_id' => $this->userId,
            'financial_year_id' => $this->financialYearId,
            'document_type_id' => $this->documentTypeId,
            'storage_disk' => 'local',
            'object_key' => 'db009-016/test-document.pdf',
            'content_sha256' => hash('sha256', 'db009-016-test-document'),
            'original_filename' => 'db009-016-test-document.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'uploaded_at' => now(),
            'provenance' => json_encode([
                'source' => 'automated-test',
            ]),
            'status' => 'active',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DB009 - document_extractions
    |--------------------------------------------------------------------------
    */

    public function test_db009_document_extraction_can_be_created(): void
    {
        $id = (string) Str::uuid();

        DB::table('document_extractions')->insert([
            'id' => $id,
            'document_id' => $this->documentId,
            'provider' => 'test-provider',
            'model' => 'test-model',
            'model_version' => '1.0',
            'extraction_version' => '1.0',
            'status' => 'completed',
            'correlation_id' => (string) Str::uuid(),
            'started_at' => now(),
            'completed_at' => now(),
            'output' => json_encode([
                'text' => 'DB009 test extraction',
            ]),
            'quality_metadata' => json_encode([
                'confidence' => 0.98,
            ]),
            'error_metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('document_extractions', [
            'id' => $id,
            'document_id' => $this->documentId,
            'provider' => 'test-provider',
            'status' => 'completed',
        ]);
    }

    public function test_db009_document_extraction_requires_existing_document(): void
    {
        $this->expectException(QueryException::class);

        DB::table('document_extractions')->insert([
            'id' => (string) Str::uuid(),
            'document_id' => (string) Str::uuid(),
            'provider' => 'test-provider',
            'extraction_version' => '1.0',
            'status' => 'failed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DB010 - transactions
    |--------------------------------------------------------------------------
    */

    public function test_db010_transaction_can_be_created(): void
    {
        $id = (string) Str::uuid();

        DB::table('transactions')->insert([
            'id' => $id,
            'user_id' => $this->userId,
            'financial_year_id' => $this->financialYearId,
            'document_id' => $this->documentId,
            'transaction_date' => '2025-08-15',
            'settlement_date' => '2025-08-16',
            'description' => 'DB010 test transaction',
            'direction' => 'debit',
            'amount' => 125.50,
            'currency' => 'AUD',
            'source_system' => 'test',
            'external_transaction_id' => 'DB010-TEST-001',
            'provenance' => json_encode([
                'source' => 'automated-test',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $id,
            'user_id' => $this->userId,
            'financial_year_id' => $this->financialYearId,
            'amount' => 125.50,
            'currency' => 'AUD',
        ]);
    }

    public function test_db010_transaction_rejects_negative_amount(): void
    {
        $this->expectException(QueryException::class);

        DB::table('transactions')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $this->userId,
            'financial_year_id' => $this->financialYearId,
            'transaction_date' => '2025-08-15',
            'direction' => 'debit',
            'amount' => -1.00,
            'currency' => 'AUD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DB011 - expenses / income
    |--------------------------------------------------------------------------
    */

    public function test_db011_expense_can_be_created(): void
    {
        $transactionId = $this->createTransaction('DB011-EXPENSE-001');

        $expenseId = (string) Str::uuid();

        DB::table('expenses')->insert([
            'id' => $expenseId,
            'transaction_id' => $transactionId,
            'user_id' => $this->userId,
            'financial_year_id' => $this->financialYearId,
            'amount' => 75.25,
            'source_system' => 'test',
            'external_id' => 'DB011-EXPENSE-001',
            'metadata' => json_encode([
                'test' => true,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('expenses', [
            'id' => $expenseId,
            'transaction_id' => $transactionId,
            'amount' => 75.25,
        ]);
    }

    public function test_db011_expense_rejects_negative_amount(): void
    {
        $transactionId = $this->createTransaction('DB011-EXPENSE-NEGATIVE');

        $this->expectException(QueryException::class);

        DB::table('expenses')->insert([
            'id' => (string) Str::uuid(),
            'transaction_id' => $transactionId,
            'user_id' => $this->userId,
            'financial_year_id' => $this->financialYearId,
            'amount' => -10.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_db011_income_can_be_created(): void
    {
        $transactionId = $this->createTransaction('DB011-INCOME-001');

        $incomeId = (string) Str::uuid();

        DB::table('income')->insert([
            'id' => $incomeId,
            'transaction_id' => $transactionId,
            'user_id' => $this->userId,
            'financial_year_id' => $this->financialYearId,
            'amount' => 2500.00,
            'source_system' => 'test',
            'external_id' => 'DB011-INCOME-001',
            'metadata' => json_encode([
                'test' => true,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('income', [
            'id' => $incomeId,
            'transaction_id' => $transactionId,
            'amount' => 2500.00,
        ]);
    }

    public function test_db011_income_rejects_negative_amount(): void
    {
        $transactionId = $this->createTransaction('DB011-INCOME-NEGATIVE');

        $this->expectException(QueryException::class);

        DB::table('income')->insert([
            'id' => (string) Str::uuid(),
            'transaction_id' => $transactionId,
            'user_id' => $this->userId,
            'financial_year_id' => $this->financialYearId,
            'amount' => -5.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DB012 - properties / property_periods
    |--------------------------------------------------------------------------
    */

    public function test_db012_property_and_period_can_be_created(): void
    {
        $propertyId = (string) Str::uuid();
        $periodId = (string) Str::uuid();

        DB::table('properties')->insert([
            'id' => $propertyId,
            'user_id' => $this->userId,
            'reference_code' => 'DB012-TEST-PROPERTY',
            'address_line_1' => '1 Test Street',
            'suburb' => 'Sydney',
            'state' => 'NSW',
            'postcode' => '2000',
            'country_code' => 'AU',
            'location_metadata' => json_encode([
                'source' => 'automated-test',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('property_periods')->insert([
            'id' => $periodId,
            'property_id' => $propertyId,
            'period_start' => '2025-07-01',
            'period_end' => '2026-06-30',
            'use_type' => 'investment',
            'ownership_percentage' => 100.0000,
            'provenance' => json_encode([
                'source' => 'automated-test',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('properties', [
            'id' => $propertyId,
            'user_id' => $this->userId,
        ]);

        $this->assertDatabaseHas('property_periods', [
            'id' => $periodId,
            'property_id' => $propertyId,
            'ownership_percentage' => 100.0000,
        ]);
    }

    public function test_db012_property_period_rejects_invalid_dates(): void
    {
        $propertyId = $this->createProperty('DB012-INVALID-DATE');

        $this->expectException(QueryException::class);

        DB::table('property_periods')->insert([
            'id' => (string) Str::uuid(),
            'property_id' => $propertyId,
            'period_start' => '2026-07-01',
            'period_end' => '2026-06-30',
            'use_type' => 'investment',
            'ownership_percentage' => 100.0000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_db012_property_period_rejects_ownership_above_100(): void
    {
        $propertyId = $this->createProperty('DB012-INVALID-OWNERSHIP');

        $this->expectException(QueryException::class);

        DB::table('property_periods')->insert([
            'id' => (string) Str::uuid(),
            'property_id' => $propertyId,
            'period_start' => '2025-07-01',
            'period_end' => '2026-06-30',
            'use_type' => 'investment',
            'ownership_percentage' => 100.0001,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_db012_property_period_rejects_overlapping_periods(): void
    {
        $propertyId = $this->createProperty('DB012-OVERLAP');

        DB::table('property_periods')->insert([
            'id' => (string) Str::uuid(),
            'property_id' => $propertyId,
            'period_start' => '2025-07-01',
            'period_end' => '2026-06-30',
            'use_type' => 'investment',
            'ownership_percentage' => 100.0000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('property_periods')->insert([
            'id' => (string) Str::uuid(),
            'property_id' => $propertyId,
            'period_start' => '2026-01-01',
            'period_end' => '2026-12-31',
            'use_type' => 'private',
            'ownership_percentage' => 100.0000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DB013 - investments
    |--------------------------------------------------------------------------
    */

    public function test_db013_investment_can_be_created(): void
    {
        $investmentId = (string) Str::uuid();

        DB::table('investments')->insert([
            'id' => $investmentId,
            'user_id' => $this->userId,
            'investment_type' => 'shares',
            'acquisition_date' => '2025-08-01',
            'disposal_date' => '2026-05-01',
            'quantity' => 100.00000000,
            'ownership_percentage' => 50.0000,
            'cost_base' => 10000.00,
            'incidental_costs' => 100.00,
            'proceeds' => 12000.00,
            'source_system' => 'test',
            'external_id' => 'DB013-TEST-001',
            'metadata' => json_encode([
                'source' => 'automated-test',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('investments', [
            'id' => $investmentId,
            'user_id' => $this->userId,
            'investment_type' => 'shares',
            'ownership_percentage' => 50.0000,
        ]);
    }

    public function test_db013_investment_rejects_invalid_disposal_date(): void
    {
        $this->expectException(QueryException::class);

        DB::table('investments')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $this->userId,
            'investment_type' => 'shares',
            'acquisition_date' => '2026-06-01',
            'disposal_date' => '2026-05-01',
            'quantity' => 10.00000000,
            'ownership_percentage' => 100.0000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_db013_investment_rejects_ownership_above_100(): void
    {
        $this->expectException(QueryException::class);

        DB::table('investments')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $this->userId,
            'investment_type' => 'shares',
            'acquisition_date' => '2025-08-01',
            'quantity' => 10.00000000,
            'ownership_percentage' => 100.0001,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DB014 - financial_record_documents
    |--------------------------------------------------------------------------
    */

    public function test_db014_financial_record_document_can_be_created(): void
    {
        $transactionId = $this->createTransaction('DB014-TEST-001');
        $relationId = (string) Str::uuid();

        DB::table('financial_record_documents')->insert([
            'id' => $relationId,
            'document_id' => $this->documentId,
            'transaction_id' => $transactionId,
            'relation_type' => 'source',
            'metadata' => json_encode([
                'source' => 'automated-test',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('financial_record_documents', [
            'id' => $relationId,
            'document_id' => $this->documentId,
            'transaction_id' => $transactionId,
            'relation_type' => 'source',
        ]);
    }

    public function test_db014_duplicate_document_transaction_relation_is_rejected(): void
    {
        $transactionId = $this->createTransaction('DB014-DUPLICATE');

        DB::table('financial_record_documents')->insert([
            'id' => (string) Str::uuid(),
            'document_id' => $this->documentId,
            'transaction_id' => $transactionId,
            'relation_type' => 'source',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('financial_record_documents')->insert([
            'id' => (string) Str::uuid(),
            'document_id' => $this->documentId,
            'transaction_id' => $transactionId,
            'relation_type' => 'source',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DB015 - reviews
    |--------------------------------------------------------------------------
    */

    public function test_db015_review_can_be_created_with_document_target(): void
    {
        $reviewId = (string) Str::uuid();

        DB::table('reviews')->insert([
            'id' => $reviewId,
            'reviewer_id' => $this->userId,
            'document_id' => $this->documentId,
            'document_extraction_id' => null,
            'transaction_id' => null,
            'review_type' => 'classification',
            'status' => 'pending',
            'decision' => null,
            'reviewer_notes' => 'DB015 test review',
            'supersedes_review_id' => null,
            'completed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('reviews', [
            'id' => $reviewId,
            'reviewer_id' => $this->userId,
            'document_id' => $this->documentId,
            'review_type' => 'classification',
        ]);

        $this->reviewId = $reviewId;
    }

    public function test_db015_review_requires_at_least_one_target(): void
    {
        $this->expectException(QueryException::class);

        DB::table('reviews')->insert([
            'id' => (string) Str::uuid(),
            'reviewer_id' => $this->userId,
            'document_id' => null,
            'document_extraction_id' => null,
            'transaction_id' => null,
            'review_type' => 'classification',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_db015_review_can_supersede_an_existing_review(): void
    {
        $firstReviewId = (string) Str::uuid();

        DB::table('reviews')->insert([
            'id' => $firstReviewId,
            'reviewer_id' => $this->userId,
            'document_id' => $this->documentId,
            'review_type' => 'classification',
            'status' => 'completed',
            'decision' => 'accepted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $secondReviewId = (string) Str::uuid();

        DB::table('reviews')->insert([
            'id' => $secondReviewId,
            'reviewer_id' => $this->userId,
            'document_id' => $this->documentId,
            'review_type' => 'classification',
            'status' => 'pending',
            'supersedes_review_id' => $firstReviewId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('reviews', [
            'id' => $secondReviewId,
            'supersedes_review_id' => $firstReviewId,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DB016 - audit_events
    |--------------------------------------------------------------------------
    */

    public function test_db016_audit_event_can_be_inserted(): void
    {
        $auditId = (string) Str::uuid();

        DB::table('audit_events')->insert([
            'id' => $auditId,
            'actor_user_id' => $this->userId,
            'event_type' => 'db016.test.created',
            'target_type' => 'TestDocument',
            'target_id' => $this->documentId,
            'occurred_at' => now(),
            'correlation_id' => (string) Str::uuid(),
            'request_id' => 'db016-test-request',
            'source_ip' => '127.0.0.1',
            'schema_version' => '1',
            'metadata' => json_encode([
                'test' => true,
            ]),
            'snapshot' => json_encode([
                'status' => 'created',
            ]),
        ]);

        $this->assertDatabaseHas('audit_events', [
            'id' => $auditId,
            'event_type' => 'db016.test.created',
            'actor_user_id' => $this->userId,
        ]);
    }

    public function test_db016_audit_event_cannot_be_updated(): void
    {
        $auditId = $this->createAuditEvent();

        $this->expectException(QueryException::class);

        DB::table('audit_events')
            ->where('id', $auditId)
            ->update([
                'event_type' => 'db016.test.modified',
            ]);
    }

    public function test_db016_audit_event_cannot_be_deleted(): void
    {
        $auditId = $this->createAuditEvent();

        $this->expectException(QueryException::class);

        DB::table('audit_events')
            ->where('id', $auditId)
            ->delete();
    }

    /*
    |--------------------------------------------------------------------------
    | Test Helpers
    |--------------------------------------------------------------------------
    */

    private function createTransaction(string $externalId): string
    {
        $id = (string) Str::uuid();

        DB::table('transactions')->insert([
            'id' => $id,
            'user_id' => $this->userId,
            'financial_year_id' => $this->financialYearId,
            'document_id' => $this->documentId,
            'transaction_date' => '2025-08-15',
            'description' => 'Automated DB009-016 test transaction',
            'direction' => 'debit',
            'amount' => 100.00,
            'currency' => 'AUD',
            'source_system' => 'automated-test',
            'external_transaction_id' => $externalId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function createProperty(string $referenceCode): string
    {
        $id = (string) Str::uuid();

        DB::table('properties')->insert([
            'id' => $id,
            'user_id' => $this->userId,
            'reference_code' => $referenceCode,
            'address_line_1' => '1 Test Street',
            'suburb' => 'Sydney',
            'state' => 'NSW',
            'postcode' => '2000',
            'country_code' => 'AU',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function createAuditEvent(): string
    {
        $id = (string) Str::uuid();

        DB::table('audit_events')->insert([
            'id' => $id,
            'actor_user_id' => $this->userId,
            'event_type' => 'db016.test.created',
            'target_type' => 'TestDocument',
            'target_id' => $this->documentId,
            'occurred_at' => now(),
            'schema_version' => '1',
            'metadata' => json_encode([
                'test' => true,
            ]),
            'snapshot' => json_encode([
                'status' => 'created',
            ]),
        ]);

        return $id;
    }
}
