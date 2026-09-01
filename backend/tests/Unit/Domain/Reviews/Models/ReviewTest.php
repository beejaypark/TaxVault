<?php

namespace Tests\Unit\Domain\Reviews\Models;

use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentExtraction;
use App\Domain\Reviews\Models\Review;
use App\Domain\Shared\Models\UuidModel;
use App\Domain\Transactions\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    public function test_review_uses_uuid_model(): void
    {
        $review = new Review();

        $this->assertInstanceOf(UuidModel::class, $review);
        $this->assertFalse($review->getIncrementing());
        $this->assertSame('string', $review->getKeyType());
        $this->assertSame('reviews', $review->getTable());
    }

    public function test_review_has_expected_fillable_attributes(): void
    {
        $review = new Review();

        $this->assertSame([
            'reviewer_id',
            'document_id',
            'document_extraction_id',
            'transaction_id',
            'review_type',
            'status',
            'decision',
            'reviewer_notes',
            'supersedes_review_id',
            'completed_at',
        ], $review->getFillable());
    }

    public function test_review_casts_completed_at_to_datetime(): void
    {
        $review = new Review();

        $casts = $review->getCasts();

        $this->assertArrayHasKey('completed_at', $casts);
        $this->assertSame('datetime', $casts['completed_at']);
    }

    public function test_review_belongs_to_reviewer(): void
    {
        $relation = (new Review())->reviewer();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertInstanceOf(User::class, $relation->getRelated());
        $this->assertSame('reviewer_id', $relation->getForeignKeyName());
    }

    public function test_review_belongs_to_document(): void
    {
        $relation = (new Review())->document();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertInstanceOf(Document::class, $relation->getRelated());
        $this->assertSame('document_id', $relation->getForeignKeyName());
    }

    public function test_review_belongs_to_document_extraction(): void
    {
        $relation = (new Review())->extraction();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertInstanceOf(DocumentExtraction::class, $relation->getRelated());
        $this->assertSame(
            'document_extraction_id',
            $relation->getForeignKeyName()
        );
    }

    public function test_review_belongs_to_transaction(): void
    {
        $relation = (new Review())->transaction();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertInstanceOf(Transaction::class, $relation->getRelated());
        $this->assertSame('transaction_id', $relation->getForeignKeyName());
    }

    public function test_review_can_reference_superseded_review(): void
    {
        $relation = (new Review())->supersededReview();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertInstanceOf(Review::class, $relation->getRelated());
        $this->assertSame(
            'supersedes_review_id',
            $relation->getForeignKeyName()
        );
    }
}
