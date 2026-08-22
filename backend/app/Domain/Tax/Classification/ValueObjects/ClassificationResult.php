<?php

namespace App\Domain\Tax\Classification\ValueObjects;

final readonly class ClassificationResult
{
    public function __construct(
        public string $taxonomyVersion,
        public ?string $categoryId,
        public ?string $subcategoryId,
        public string $confidence,
        public string $source,
        public array $provenance,
        public bool $requiresReview,
    ) {}
}
