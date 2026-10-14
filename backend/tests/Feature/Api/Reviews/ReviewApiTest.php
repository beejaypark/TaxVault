<?php

namespace Tests\Feature\Api\Reviews;

use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentExtraction;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\Evidence\Models\TaxEvidence;
use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Reviews\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReviewApiTest extends TestCase
{
    use RefreshDatabase;

    private function createReview(
        User $user,
        string $status = 'pending',
        string $reviewType = 'confidence',
    ): Review {
        $financialYear = FinancialYear::query()->firstOrCreate(
            [
                'user_id' => $user->getKey(),
                'year_code' => '2026-27',
            ],
            [
                'start_date' => '2026-07-01',
                'end_date' => '2027-06-30',
                'status' => 'active',
            ],
        );

        $documentType = DocumentType::query()->create([
            'code' => 'invoice-' . Str::lower(Str::random(6)),
            'name' => 'Invoice',
            'description' => 'Invoice document',
            'metadata' => [],
            'status' => 'active',
        ]);

        $document = Document::query()->create([
            'user_id' => $user->getKey(),
            'financial_year_id' => $financialYear->getKey(),
            'document_type_id' => $documentType->getKey(),
            'storage_disk' => 'documents',
            'object_key' => '2026-27/' . Str::uuid() . '.pdf',
            'content_sha256' => hash('sha256', (string) Str::uuid()),
            'original_filename' => 'review.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
            'uploaded_at' => now(),
            'status' => 'active',
        ]);

        $extraction = DocumentExtraction::query()->create([
            'document_id' => $document->getKey(),
            'provider' => 'test-confidence',
            'model' => 'test-ocr',
            'model_version' => '1',
            'extraction_version' => 'ocr-v1',
            'status' => 'completed',
            'correlation_id' => (string) Str::uuid(),
            'started_at' => now(),
            'completed_at' => now(),
            'output' => [],
            'quality_metadata' => [],
            'error_metadata' => null,
        ]);

        return Review::query()->create([
            'reviewer_id' => $user->getKey(),
            'document_id' => $document->getKey(),
            'document_extraction_id' => $extraction->getKey(),
            'transaction_id' => null,
            'review_type' => $reviewType,
            'status' => $status,
            'decision' => $status === 'completed' ? 'approve' : null,
            'reviewer_notes' => 'OCR confidence review required for fields: amount',
            'supersedes_review_id' => null,
            'completed_at' => $status === 'completed' ? now() : null,
        ]);
    }

    private function setNormalizedFields(
        Review $review,
        array $fields,
    ): void {
        $extraction = $review->extraction()->firstOrFail();

        $extraction->update([
            'output' => [
                'normalized' => [
                    'schema_version' => 'ocr-v1',
                    'fields' => $fields,
                ],
            ],
        ]);
    }

    public function test_authenticated_user_can_list_own_reviews(): void
    {
        $user = User::factory()->create();

        $review = $this->createReview($user);

        $response = $this->actingAs($user)
            ->getJson('/api/reviews');

        $response
            ->assertOk();


        $response
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $review->id)
            ->assertJsonPath('data.0.reviewer_id', $user->id)
            ->assertJsonPath('data.0.status', 'pending');
    }

    public function test_user_cannot_see_another_users_reviews(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $review = $this->createReview($owner);

        $response = $this->actingAs($otherUser)
            ->getJson('/api/reviews');

        $response
            ->assertOk();


        $response
            ->assertJsonCount(0, 'data');

        $this->actingAs($otherUser)
            ->getJson('/api/reviews/' . $review->id)
            ->assertNotFound();
    }

    public function test_unauthenticated_user_cannot_access_reviews(): void
    {
        $this->getJson('/api/reviews')
            ->assertUnauthorized();

        $this->getJson('/api/reviews/' . Str::uuid())
            ->assertUnauthorized();

        $this->postJson('/api/reviews/' . Str::uuid() . '/decision', [
            'decision' => 'approve',
        ])->assertUnauthorized();

        $this->postJson('/api/reviews/' . Str::uuid() . '/correction', [
            'field_path' => 'amount',
            'value' => 100,
        ])->assertUnauthorized();
    }

    public function test_authenticated_user_can_show_own_review(): void
    {
        $user = User::factory()->create();

        $review = $this->createReview($user);


        $response = $this->actingAs($user)
            ->getJson('/api/reviews/' . $review->id);

        $response
            ->assertOk();


        $response
            ->assertJsonPath('data.id', $review->id)
            ->assertJsonPath('data.review_type', 'confidence')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath(
                'data.reviewer_notes',
                'OCR confidence review required for fields: amount'
            );
    }

    public function test_show_review_includes_active_authoritative_corrections(): void
    {
        $user = User::factory()->create();

        $review = $this->createReview($user);

        $this->setNormalizedFields($review, [
            'amount' => [
                'value' => 125.50,
                'confidence' => 72.0,
                'source' => 'ocr',
            ],
        ]);

        $evidence = TaxEvidence::query()->create([
            'user_id' => $user->id,
            'financial_year_id' => $review->document->financial_year_id,
            'document_id' => $review->document_id,
            'extraction_id' => $review->document_extraction_id,
            'evidence_type' => 'review_correction',
            'source_type' => 'document_extraction',
            'source_id' => $review->document_extraction_id,
            'field_path' => 'amount',
            'extracted_value' => [
                'value' => 150.75,
                'source' => 'reviewer',
            ],
            'status' => 'active',
            'verification_status' => 'verified',
            'confidence' => 100.00,
            'verified_at' => now(),
        ]);

        $this->assertSame(
            1,
            TaxEvidence::query()
                ->where('extraction_id', $review->document_extraction_id)
                ->where('document_id', $review->document_id)
                ->where('evidence_type', 'review_correction')
                ->where('status', 'active')
                ->count()
        );

        $this->assertSame(
            1,
            $review->corrections()->count()
        );

        $response = $this->actingAs($user)
            ->getJson('/api/reviews/' . $review->id);

        $response
            ->assertOk();


        $response
            ->assertJsonPath(
                'data.id',
                $review->id
            )
            ->assertJsonCount(
                1,
                'data.corrections'
            )
            ->assertJsonPath(
                'data.corrections.0.id',
                $evidence->id
            )
            ->assertJsonPath(
                'data.corrections.0.field_path',
                'amount'
            )
            ->assertJsonPath(
                'data.corrections.0.extracted_value.value',
                150.75
            )
            ->assertJsonPath(
                'data.corrections.0.extracted_value.source',
                'reviewer'
            )
            ->assertJsonPath(
                'data.corrections.0.status',
                'active'
            )
            ->assertJsonPath(
                'data.corrections.0.verification_status',
                'verified'
            );
    }
    public function test_authenticated_user_can_complete_pending_review(): void
    {
        $user = User::factory()->create();

        $review = $this->createReview($user);

        $response = $this->actingAs($user)
            ->postJson('/api/reviews/' . $review->id . '/decision', [
                'decision' => 'approve',
                'reviewer_notes' => 'Reviewed and approved.',
            ]);

        $response
            ->assertOk();


        $response
            ->assertJsonPath('data.id', $review->id)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.decision', 'approve')
            ->assertJsonPath(
                'data.reviewer_notes',
                'Reviewed and approved.'
            );

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'reviewer_id' => $user->id,
            'status' => 'completed',
            'decision' => 'approve',
            'reviewer_notes' => 'Reviewed and approved.',
        ]);

        $this->assertNotNull(
            Review::query()->findOrFail($review->id)->completed_at
        );
    }

    public function test_user_cannot_complete_another_users_review(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $review = $this->createReview($owner);

        $this->actingAs($otherUser)
            ->postJson('/api/reviews/' . $review->id . '/decision', [
                'decision' => 'approve',
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'reviewer_id' => $owner->id,
            'status' => 'pending',
        ]);
    }

    public function test_completed_review_cannot_be_decided_again(): void
    {
        $user = User::factory()->create();

        $review = $this->createReview(
            $user,
            status: 'completed',
        );

        $this->actingAs($user)
            ->postJson('/api/reviews/' . $review->id . '/decision', [
                'decision' => 'reject',
            ])
            ->assertStatus(409)
            ->assertJson([
                'message' => 'Review is not pending.',
            ]);

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'status' => 'completed',
            'decision' => 'approve',
        ]);
    }

    public function test_review_decision_requires_decision(): void
    {
        $user = User::factory()->create();

        $review = $this->createReview($user);

        $this->actingAs($user)
            ->postJson('/api/reviews/' . $review->id . '/decision', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'decision',
            ]);
    }

    public function test_reviews_can_be_filtered_by_status(): void
    {
        $user = User::factory()->create();

        $pending = $this->createReview($user, 'pending');

        $completed = $this->createReview(
            User::factory()->create()
        );

        $completed->reviewer_id = $user->id;
        $completed->status = 'completed';
        $completed->decision = 'approve';
        $completed->completed_at = now();
        $completed->save();

        $response = $this->actingAs($user)
            ->getJson('/api/reviews?status=pending');

        $response
            ->assertOk();


        $response
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pending->id)
            ->assertJsonPath('data.0.status', 'pending');
    }

    public function test_reviews_can_be_filtered_by_review_type(): void
    {
        $user = User::factory()->create();

        $confidenceReview = $this->createReview(
            $user,
            reviewType: 'confidence',
        );

        $transactionReview = $this->createReview(
            $user,
            reviewType: 'transaction',
        );

        $response = $this->actingAs($user)
            ->getJson('/api/reviews?review_type=confidence');

        $response
            ->assertOk();


        $response
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $confidenceReview->id
            )
            ->assertJsonPath(
                'data.0.review_type',
                'confidence'
            );

        $this->assertNotSame(
            $confidenceReview->id,
            $transactionReview->id
        );
    }

    public function test_authenticated_user_can_create_authoritative_correction(): void
    {
        $user = User::factory()->create();

        $review = $this->createReview($user);

        $this->setNormalizedFields($review, [
            'amount' => [
                'value' => 125.50,
                'confidence' => 72.0,
                'source' => 'ocr',
            ],
        ]);

        $response = $this->actingAs($user)
            ->postJson(
                '/api/reviews/' . $review->id . '/correction',
                [
                    'field_path' => 'amount',
                    'value' => 150.75,
                ]
            );

        $response
            ->assertOk();


        $response
            ->assertJsonPath(
                'data.review.id',
                $review->id
            )
            ->assertJsonPath(
                'data.review.status',
                'pending'
            )
            ->assertJsonPath(
                'data.evidence.evidence_type',
                'review_correction'
            )
            ->assertJsonPath(
                'data.evidence.source_type',
                'document_extraction'
            )
            ->assertJsonPath(
                'data.evidence.field_path',
                'amount'
            )
            ->assertJsonPath(
                'data.evidence.extracted_value.value',
                150.75
            )
            ->assertJsonPath(
                'data.evidence.extracted_value.source',
                'reviewer'
            )
            ->assertJsonPath(
                'data.evidence.verification_status',
                'verified'
            )
            ->assertJsonPath(
                'data.evidence.status',
                'active'
            );

        $this->assertDatabaseHas('tax_evidences', [
            'id' => $response->json('data.evidence.id'),
            'user_id' => $user->id,
            'financial_year_id' => $review->document->financial_year_id,
            'document_id' => $review->document_id,
            'extraction_id' => $review->document_extraction_id,
            'evidence_type' => 'review_correction',
            'source_type' => 'document_extraction',
            'field_path' => 'amount',
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'status' => 'pending',
        ]);
    }

    public function test_user_cannot_create_correction_for_another_users_review(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $review = $this->createReview($owner);

        $this->setNormalizedFields($review, [
            'amount' => [
                'value' => 125.50,
                'confidence' => 72.0,
                'source' => 'ocr',
            ],
        ]);

        $this->actingAs($otherUser)
            ->postJson(
                '/api/reviews/' . $review->id . '/correction',
                [
                    'field_path' => 'amount',
                    'value' => 150.75,
                ]
            )
            ->assertNotFound();

        $this->assertDatabaseCount('tax_evidences', 0);
    }

    public function test_completed_review_cannot_create_correction(): void
    {
        $user = User::factory()->create();

        $review = $this->createReview(
            $user,
            status: 'completed',
        );

        $this->setNormalizedFields($review, [
            'amount' => [
                'value' => 125.50,
                'confidence' => 72.0,
                'source' => 'ocr',
            ],
        ]);

        $this->actingAs($user)
            ->postJson(
                '/api/reviews/' . $review->id . '/correction',
                [
                    'field_path' => 'amount',
                    'value' => 150.75,
                ]
            )
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'review',
            ]);

        $this->assertDatabaseCount('tax_evidences', 0);
    }

    public function test_correction_requires_existing_normalized_field(): void
    {
        $user = User::factory()->create();

        $review = $this->createReview($user);

        $this->setNormalizedFields($review, [
            'amount' => [
                'value' => 125.50,
                'confidence' => 72.0,
                'source' => 'ocr',
            ],
        ]);

        $this->actingAs($user)
            ->postJson(
                '/api/reviews/' . $review->id . '/correction',
                [
                    'field_path' => 'invoice_number',
                    'value' => 'INV-001',
                ]
            )
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'field_path',
            ]);

        $this->assertDatabaseCount('tax_evidences', 0);
    }

    public function test_repeated_correction_supersedes_previous_active_correction(): void
    {
        $user = User::factory()->create();

        $review = $this->createReview($user);

        $this->setNormalizedFields($review, [
            'amount' => [
                'value' => 125.50,
                'confidence' => 72.0,
                'source' => 'ocr',
            ],
        ]);

        $firstResponse = $this->actingAs($user)
            ->postJson(
                '/api/reviews/' . $review->id . '/correction',
                [
                    'field_path' => 'amount',
                    'value' => 150.75,
                ]
            )
            ->assertOk();

        $firstEvidenceId = $firstResponse->json(
            'data.evidence.id'
        );

        $secondResponse = $this->actingAs($user)
            ->postJson(
                '/api/reviews/' . $review->id . '/correction',
                [
                    'field_path' => 'amount',
                    'value' => 175.25,
                ]
            )
            ->assertOk();

        $secondEvidenceId = $secondResponse->json(
            'data.evidence.id'
        );

        $this->assertNotSame(
            $firstEvidenceId,
            $secondEvidenceId
        );

        $this->assertDatabaseHas('tax_evidences', [
            'id' => $firstEvidenceId,
            'evidence_type' => 'review_correction',
            'field_path' => 'amount',
            'status' => 'superseded',
        ]);

        $this->assertDatabaseHas('tax_evidences', [
            'id' => $secondEvidenceId,
            'evidence_type' => 'review_correction',
            'field_path' => 'amount',
            'status' => 'active',
        ]);

        $this->assertSame(
            1,
            TaxEvidence::query()
                ->where('user_id', $user->id)
                ->where('evidence_type', 'review_correction')
                ->where('field_path', 'amount')
                ->where('status', 'active')
                ->count()
        );
    }

    public function test_correction_does_not_mutate_original_document_extraction(): void
    {
        $user = User::factory()->create();

        $review = $this->createReview($user);

        $originalOutput = [
            'normalized' => [
                'schema_version' => 'ocr-v1',
                'fields' => [
                    'amount' => [
                        'value' => 125.50,
                        'confidence' => 72.0,
                        'source' => 'ocr',
                    ],
                ],
            ],
        ];

        $review->extraction()->update([
            'output' => $originalOutput,
        ]);

        $this->actingAs($user)
            ->postJson(
                '/api/reviews/' . $review->id . '/correction',
                [
                    'field_path' => 'amount',
                    'value' => 150.75,
                ]
            )
            ->assertOk();

        $extraction = DocumentExtraction::query()
            ->findOrFail($review->document_extraction_id);

        $this->assertEquals(
            $originalOutput,
            $extraction->output
        );

        $this->assertDatabaseHas('document_extractions', [
            'id' => $review->document_extraction_id,
        ]);
    }
}

