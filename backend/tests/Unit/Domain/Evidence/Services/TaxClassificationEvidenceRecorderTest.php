<?php

namespace Tests\Unit\Domain\Evidence\Services;

use App\Domain\Evidence\Models\TaxEvidence;
use App\Domain\Evidence\Services\TaxClassificationEvidenceRecorder;
use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Tax\Classification\ValueObjects\ClassificationResult;
use App\Domain\Taxonomy\Models\TaxCategory;
use App\Domain\Taxonomy\Models\TaxSubcategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class TaxClassificationEvidenceRecorderTest extends TestCase
{
    use RefreshDatabase;

    private function recorder(): TaxClassificationEvidenceRecorder
    {
        return new TaxClassificationEvidenceRecorder();
    }

    /**
     * Create all database records required by tax_evidences foreign keys.
     *
     * @return array{
     *     user: User,
     *     financialYear: FinancialYear,
     *     category: TaxCategory,
     *     subcategory: TaxSubcategory
     * }
     */
    private function createEvidenceContext(): array
    {
        $user = User::factory()->create();

        $financialYear = FinancialYear::create([
            'user_id' => $user->id,
            'year_code' => '2026-27',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'status' => 'active',
        ]);

        $category = TaxCategory::create([
            'code' => 'TEST-CATEGORY',
            'name' => 'Test Category',
            'sort_order' => 1,
            'status' => 'active',
            'taxonomy_version' => '2026.1',
        ]);

        $subcategory = TaxSubcategory::create([
            'tax_category_id' => $category->id,
            'code' => 'TEST-SUBCATEGORY',
            'name' => 'Test Subcategory',
            'sort_order' => 1,
            'status' => 'active',
            'taxonomy_version' => '2026.1',
        ]);

        return [
            'user' => $user,
            'financialYear' => $financialYear,
            'category' => $category,
            'subcategory' => $subcategory,
        ];
    }

    /**
     * Build a classification result.
     *
     * Passing null explicitly for $categoryId means the result really
     * contains no tax category. This is required by the validation test.
     */
    private function classificationResult(
        string $confidence = '0.95',
        ?string $categoryId = null,
        ?string $subcategoryId = null,
        string $source = 'rule',
        array $provenance = [],
        bool $requiresReview = false,
    ): ClassificationResult {
        return new ClassificationResult(
            taxonomyVersion: '2026.1',
            categoryId: $categoryId,
            subcategoryId: $subcategoryId,
            confidence: $confidence,
            source: $source,
            provenance: $provenance,
            requiresReview: $requiresReview,
        );
    }

    public function test_classification_result_is_recorded_as_evidence(): void
    {
        $context = $this->createEvidenceContext();

        $user = $context['user'];
        $financialYear = $context['financialYear'];
        $category = $context['category'];
        $subcategory = $context['subcategory'];

        $classificationResult = $this->classificationResult(
            confidence: '0.95',
            categoryId: $category->id,
            subcategoryId: $subcategory->id,
            source: 'rule',
            provenance: [
                'rule_code' => 'CLASSIFY-OFFICE-SUPPLIES',
                'matched_facts' => [
                    'expense_type' => 'office_supplies',
                    'business_purpose' => true,
                ],
            ],
            requiresReview: false,
        );

        $evidence = $this->recorder()->record(
            $user->id,
            $financialYear->id,
            $classificationResult,
            'AUS-TAX-2026.1',
            'tax-engine-1.0',
        );

        $this->assertInstanceOf(
            TaxEvidence::class,
            $evidence,
        );

        $this->assertSame(
            $user->id,
            $evidence->user_id,
        );

        $this->assertSame(
            $financialYear->id,
            $evidence->financial_year_id,
        );

        $this->assertSame(
            'classification',
            $evidence->evidence_type,
        );

        $this->assertSame(
            'classification',
            $evidence->source_type,
        );

        $this->assertSame(
            $category->id,
            $evidence->tax_category_id,
        );

        $this->assertSame(
            $subcategory->id,
            $evidence->tax_subcategory_id,
        );

        $this->assertSame(
            '2026.1',
            $evidence->rule_version,
        );

        $this->assertSame(
            'AUS-TAX-2026.1',
            $evidence->ruleset_version,
        );

        $this->assertSame(
            'tax-engine-1.0',
            $evidence->engine_version,
        );

        $this->assertSame(
            'rule',
            $evidence->rule_source,
        );

        $this->assertSame(
            '95.00',
            $evidence->confidence,
        );

        $this->assertSame(
            'CLASSIFY-OFFICE-SUPPLIES',
            $evidence->rule_code,
        );

        $this->assertSame(
            'active',
            $evidence->status,
        );

        $this->assertSame(
            'pending',
            $evidence->verification_status,
        );
    }

    public function test_classification_provenance_is_preserved(): void
    {
        $context = $this->createEvidenceContext();

        $classificationResult = $this->classificationResult(
            confidence: '0.79',
            categoryId: $context['category']->id,
            subcategoryId: null,
            provenance: [
                'rule_code' => 'CLASSIFY-OTHER',
                'rule_taxonomy_version' => '2026.1',
                'matched_facts' => [
                    'merchant_type' => 'other',
                ],
            ],
            requiresReview: true,
        );

        $evidence = $this->recorder()->record(
            $context['user']->id,
            $context['financialYear']->id,
            $classificationResult,
            'AUS-TAX-2026.1',
            'tax-engine-1.0',
        );

        $this->assertSame(
            '2026.1',
            $evidence->rule_provenance['taxonomy_version'],
        );

        $this->assertSame(
            '0.79',
            $evidence->rule_provenance['confidence'],
        );

        $this->assertSame(
            'rule',
            $evidence->rule_provenance['source'],
        );

        $this->assertTrue(
            $evidence->rule_provenance['requires_review'],
        );

        $this->assertSame(
            'AUS-TAX-2026.1',
            $evidence->rule_provenance['ruleset_version'],
        );

        $this->assertSame(
            'tax-engine-1.0',
            $evidence->rule_provenance['engine_version'],
        );

        $this->assertSame(
            [
                'rule_code' => 'CLASSIFY-OTHER',
                'rule_taxonomy_version' => '2026.1',
                'matched_facts' => [
                    'merchant_type' => 'other',
                ],
            ],
            $evidence->rule_provenance['provenance'],
        );
    }

    public function test_classification_reason_from_provenance_is_preserved(): void
    {
        $context = $this->createEvidenceContext();

        $classificationResult = $this->classificationResult(
            confidence: '0.90',
            categoryId: $context['category']->id,
            subcategoryId: null,
            provenance: [
                'reason' => 'Matched office equipment classification rule.',
            ],
        );

        $evidence = $this->recorder()->record(
            $context['user']->id,
            $context['financialYear']->id,
            $classificationResult,
            'AUS-TAX-2026.1',
            'tax-engine-1.0',
        );

        $this->assertSame(
            'Matched office equipment classification rule.',
            $evidence->classification_reason,
        );
    }

    public function test_classification_requires_tax_category(): void
    {
        $context = $this->createEvidenceContext();

        $classificationResult = $this->classificationResult(
            confidence: '0.95',
            categoryId: null,
            subcategoryId: null,
        );

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'A tax category is required to record classification evidence.'
        );

        $this->recorder()->record(
            $context['user']->id,
            $context['financialYear']->id,
            $classificationResult,
            'AUS-TAX-2026.1',
            'tax-engine-1.0',
        );
    }
}
