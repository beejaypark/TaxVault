<?php

namespace Tests\Unit\Domain\Tax\Classification;

use App\Domain\Tax\Classification\Services\TaxClassificationService;
use PHPUnit\Framework\TestCase;

class TaxClassificationServiceTest extends TestCase
{
    private function service(): TaxClassificationService
    {
        return new TaxClassificationService([
            [
                'taxonomy_version' => '2026.1',
                'facts' => [
                    'merchant_type' => 'salary',
                    'direction' => 'credit',
                ],
                'category_id' => 'category-income',
                'subcategory_id' => 'subcategory-salary',
                'confidence' => '0.95',
            ],
            [
                'taxonomy_version' => '2025.1',
                'facts' => [
                    'merchant_type' => 'salary',
                    'direction' => 'credit',
                ],
                'category_id' => 'category-old-income',
                'subcategory_id' => 'subcategory-old-salary',
                'confidence' => '0.90',
            ],
        ]);
    }

    public function test_matching_facts_are_classified(): void
    {
        $result = $this->service()->classify(
            [
                'merchant_type' => 'salary',
                'direction' => 'credit',
            ],
            '2026.1',
        );

        $this->assertSame('category-income', $result->categoryId);
        $this->assertSame('subcategory-salary', $result->subcategoryId);
        $this->assertSame('0.95', $result->confidence);
        $this->assertSame('rule', $result->source);
        $this->assertFalse($result->requiresReview);
    }

    public function test_taxonomy_version_is_respected(): void
    {
        $result = $this->service()->classify(
            [
                'merchant_type' => 'salary',
                'direction' => 'credit',
            ],
            '2025.1',
        );

        $this->assertSame('category-old-income', $result->categoryId);
        $this->assertSame('subcategory-old-salary', $result->subcategoryId);
        $this->assertSame('2025.1', $result->taxonomyVersion);
    }

    public function test_no_matching_rule_requires_review(): void
    {
        $result = $this->service()->classify(
            [
                'merchant_type' => 'unknown',
            ],
            '2026.1',
        );

        $this->assertNull($result->categoryId);
        $this->assertNull($result->subcategoryId);
        $this->assertSame('0.00', $result->confidence);
        $this->assertSame('unclassified', $result->source);
        $this->assertTrue($result->requiresReview);
    }

    public function test_low_confidence_requires_review(): void
    {
        $service = new TaxClassificationService([
            [
                'taxonomy_version' => '2026.1',
                'facts' => [
                    'merchant_type' => 'other',
                ],
                'category_id' => 'category-other',
                'subcategory_id' => 'subcategory-other',
                'confidence' => '0.79',
            ],
        ]);

        $result = $service->classify(
            ['merchant_type' => 'other'],
            '2026.1',
        );

        $this->assertSame('0.79', $result->confidence);
        $this->assertTrue($result->requiresReview);
    }

    public function test_provenance_is_preserved(): void
    {
        $result = $this->service()->classify(
            [
                'merchant_type' => 'salary',
                'direction' => 'credit',
            ],
            '2026.1',
        );

        $this->assertSame(
            '2026.1',
            $result->provenance['rule_taxonomy_version'],
        );

        $this->assertSame(
            [
                'merchant_type' => 'salary',
                'direction' => 'credit',
            ],
            $result->provenance['matched_facts'],
        );
    }
}
