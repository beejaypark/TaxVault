<?php

namespace Tests\Unit\Domain\Tax\Golden;

use App\Domain\Tax\Apportionment\Services\ApportionmentCalculator;
use App\Domain\Tax\Classification\Services\TaxClassificationService;
use App\Domain\Tax\Deductibility\Enums\DeductibilityOutcome;
use App\Domain\Tax\Deductibility\Services\DeductibilityEvaluator;
use App\Domain\Tax\Deductibility\ValueObjects\DeductibilityFacts;
use App\Domain\Tax\GST\Enums\GstTreatment;
use App\Domain\Tax\GST\Services\GstCalculator;
use PHPUnit\Framework\TestCase;

class GoldenTaxScenarioTest extends TestCase
{
    public function test_golden_business_expense_is_fully_deductible(): void
    {
        $result = (new DeductibilityEvaluator())->evaluate(
            new DeductibilityFacts(
                businessPurpose: true,
            ),
            'fy-golden-2026',
        );

        $this->assertSame(
            DeductibilityOutcome::Deductible,
            $result->outcome,
        );
        $this->assertSame('100.00', $result->deductiblePercentage);
        $this->assertSame('fy-golden-2026', $result->financialYearId);
        $this->assertSame('TAX-004', $result->ruleId);
        $this->assertSame(
            'TaxVault deductibility rules',
            $result->source,
        );
    }

    public function test_golden_private_expense_is_not_deductible(): void
    {
        $result = (new DeductibilityEvaluator())->evaluate(
            new DeductibilityFacts(
                businessPurpose: false,
                privatePurpose: true,
            ),
            'fy-golden-2026',
        );

        $this->assertSame(
            DeductibilityOutcome::NonDeductible,
            $result->outcome,
        );
        $this->assertSame('0.00', $result->deductiblePercentage);
    }

    public function test_golden_mixed_use_expense_is_partially_deductible(): void
    {
        $result = (new DeductibilityEvaluator())->evaluate(
            new DeductibilityFacts(
                businessPurpose: true,
                businessUsePercentage: '33.33',
            ),
            'fy-golden-2026',
        );

        $this->assertSame(
            DeductibilityOutcome::Partial,
            $result->outcome,
        );
        $this->assertSame('33.33', $result->deductiblePercentage);
    }

    public function test_golden_capital_asset_is_capital_treatment(): void
    {
        $result = (new DeductibilityEvaluator())->evaluate(
            new DeductibilityFacts(
                businessPurpose: true,
                capitalAsset: true,
                immediateDeductionEligible: false,
            ),
            'fy-golden-2026',
        );

        $this->assertSame(
            DeductibilityOutcome::Capital,
            $result->outcome,
        );
        $this->assertNull($result->deductiblePercentage);
    }

    public function test_golden_taxable_gst_is_ten_percent(): void
    {
        $result = (new GstCalculator())->fromExclusive(
            '100.00',
            GstTreatment::Taxable,
        );

        $this->assertSame('100.00', $result->exclusive);
        $this->assertSame('10.00', $result->gst);
        $this->assertSame('110.00', $result->inclusive);
    }

    public function test_golden_gst_free_amount_has_zero_gst(): void
    {
        $result = (new GstCalculator())->fromExclusive(
            '100.00',
            GstTreatment::GstFree,
        );

        $this->assertSame('100.00', $result->exclusive);
        $this->assertSame('0.00', $result->gst);
        $this->assertSame('100.00', $result->inclusive);
    }

    public function test_golden_apportionment_is_deterministic(): void
    {
        $calculator = new ApportionmentCalculator();

        $first = $calculator->calculate('123.45', '33.33');
        $second = $calculator->calculate('123.45', '33.33');

        $this->assertSame('123.45', $first->total);
        $this->assertSame('33.33', $first->percentage);
        $this->assertSame('41.14', $first->allocated);
        $this->assertSame('82.31', $first->unallocated);

        $this->assertSame($first->total, $second->total);
        $this->assertSame($first->percentage, $second->percentage);
        $this->assertSame($first->allocated, $second->allocated);
        $this->assertSame($first->unallocated, $second->unallocated);
    }

    public function test_golden_classification_is_deterministic(): void
    {
        $rules = [
            [
                'taxonomy_version' => 'ATO-2026-v1',
                'facts' => [
                    'expense_type' => 'office_supplies',
                    'business_purpose' => true,
                ],
                'category_id' => 'category-office',
                'subcategory_id' => 'subcategory-stationery',
                'confidence' => '0.95',
            ],
        ];

        $service = new TaxClassificationService($rules);

        $facts = [
            'expense_type' => 'office_supplies',
            'business_purpose' => true,
        ];

        $first = $service->classify($facts, 'ATO-2026-v1');
        $second = $service->classify($facts, 'ATO-2026-v1');

        $this->assertSame('ATO-2026-v1', $first->taxonomyVersion);
        $this->assertSame('category-office', $first->categoryId);
        $this->assertSame('subcategory-stationery', $first->subcategoryId);
        $this->assertSame('0.95', $first->confidence);
        $this->assertSame('rule', $first->source);
        $this->assertFalse($first->requiresReview);

        $this->assertSame($first->taxonomyVersion, $second->taxonomyVersion);
        $this->assertSame($first->categoryId, $second->categoryId);
        $this->assertSame($first->subcategoryId, $second->subcategoryId);
        $this->assertSame($first->confidence, $second->confidence);
        $this->assertSame($first->source, $second->source);
        $this->assertSame($first->provenance, $second->provenance);
        $this->assertSame($first->requiresReview, $second->requiresReview);
    }
}
