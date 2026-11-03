<?php

namespace Tests\Feature\Api\Documents;

use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentType;
use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Transactions\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentPaymentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_confirm_payment_and_create_one_linked_expense(): void
    {
        [$user, $financialYear, $documentType] = $this->createDocumentContext();
        $document = $this->createDocument($user, $financialYear, $documentType);

        $this->actingAs($user)
            ->postJson('/api/documents/'.$document->id.'/payment', [
                'payment_date' => '2026-10-06',
                'payment_method' => 'bpay',
                'amount' => '2085.63',
            ])
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.payment_date', '2026-10-06')
            ->assertJsonPath('data.payment_method', 'bpay')
            ->assertJsonPath('data.payment_amount', '2085.63');

        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseCount('expenses', 1);
        $this->assertDatabaseHas('transactions', [
            'document_id' => $document->id,
            'user_id' => $user->id,
            'financial_year_id' => $financialYear->id,
            'transaction_date' => '2026-10-06',
            'direction' => 'expense',
            'amount' => '2085.63',
            'source_system' => 'document_payment_confirmation',
        ]);
        $this->assertDatabaseHas('expenses', [
            'user_id' => $user->id,
            'financial_year_id' => $financialYear->id,
            'amount' => '2085.63',
            'source_system' => 'document_payment_confirmation',
        ]);
    }

    public function test_repeated_confirmation_does_not_create_a_duplicate_expense(): void
    {
        [$user, $financialYear, $documentType] = $this->createDocumentContext();
        $document = $this->createDocument($user, $financialYear, $documentType);
        $payment = [
            'payment_date' => '2026-10-06',
            'payment_method' => 'bpay',
            'amount' => '2085.63',
        ];

        $this->actingAs($user)
            ->postJson('/api/documents/'.$document->id.'/payment', $payment)
            ->assertOk();

        $this->actingAs($user)
            ->postJson('/api/documents/'.$document->id.'/payment', $payment)
            ->assertUnprocessable();

        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseCount('expenses', 1);
    }

    public function test_review_approval_reuses_the_paid_documents_linked_expense(): void
    {
        [$user, $financialYear, $documentType] = $this->createDocumentContext();
        $document = $this->createDocument($user, $financialYear, $documentType);

        $this->actingAs($user)
            ->postJson('/api/documents/'.$document->id.'/payment', [
                'payment_date' => '2026-10-06',
                'payment_method' => 'bpay',
                'amount' => '2085.63',
            ])
            ->assertOk();

        $reviewId = (string) Str::uuid();
        DB::table('reviews')->insert([
            'id' => $reviewId,
            'reviewer_id' => $user->id,
            'document_id' => $document->id,
            'review_type' => 'classification',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson('/api/reviews/'.$reviewId.'/decision', [
                'decision' => 'approve',
                'expense' => [
                    'transaction_date' => '2026-10-06',
                    'direction' => 'expense',
                    'amount' => '2085.63',
                    'description' => 'Strata contribution',
                ],
            ])
            ->assertOk();

        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseCount('expenses', 1);
        $this->assertDatabaseHas('reviews', [
            'id' => $reviewId,
            'transaction_id' => DB::table('transactions')
                ->where('document_id', $document->id)
                ->value('id'),
        ]);
    }

    public function test_payment_confirmation_reuses_a_matching_expense_already_linked_to_the_document(): void
    {
        [$user, $financialYear, $documentType] = $this->createDocumentContext();
        $document = $this->createDocument($user, $financialYear, $documentType);
        $transaction = Transaction::query()->create([
            'user_id' => $user->id,
            'financial_year_id' => $financialYear->id,
            'document_id' => $document->id,
            'transaction_date' => '2026-10-01',
            'description' => 'Strata contribution',
            'direction' => 'expense',
            'amount' => '2085.63',
            'currency' => 'AUD',
            'source_system' => 'document_review',
            'provenance' => ['review_id' => 'review-123'],
        ]);

        DB::table('expenses')->insert([
            'transaction_id' => $transaction->id,
            'user_id' => $user->id,
            'financial_year_id' => $financialYear->id,
            'amount' => '2085.63',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson('/api/documents/'.$document->id.'/payment', [
                'payment_date' => '2026-10-06',
                'payment_method' => 'bpay',
                'amount' => '2085.63',
            ])
            ->assertOk();

        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseCount('expenses', 1);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'transaction_date' => '2026-10-01',
        ]);
        $this->assertSame(
            'bpay',
            $transaction->fresh()->provenance['payment_confirmation']['method'],
        );
    }

    public function test_mismatched_existing_expense_must_be_reconciled_before_payment_confirmation(): void
    {
        [$user, $financialYear, $documentType] = $this->createDocumentContext();
        $document = $this->createDocument($user, $financialYear, $documentType);
        Transaction::query()->create([
            'user_id' => $user->id,
            'financial_year_id' => $financialYear->id,
            'document_id' => $document->id,
            'transaction_date' => '2026-10-01',
            'description' => 'Strata contribution',
            'direction' => 'expense',
            'amount' => '2000.00',
            'currency' => 'AUD',
        ]);

        $this->actingAs($user)
            ->postJson('/api/documents/'.$document->id.'/payment', [
                'payment_date' => '2026-10-06',
                'payment_method' => 'bpay',
                'amount' => '2085.63',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');

        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseCount('expenses', 0);
        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'payment_status' => 'unconfirmed',
        ]);
    }

    public function test_unassigned_document_cannot_create_a_payment_expense(): void
    {
        [$user, , $documentType] = $this->createDocumentContext();
        $document = $this->createDocument($user, null, $documentType);

        $this->actingAs($user)
            ->postJson('/api/documents/'.$document->id.'/payment', [
                'payment_date' => '2026-10-06',
                'payment_method' => 'bpay',
                'amount' => '2085.63',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('document');

        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('expenses', 0);
    }

    private function createDocumentContext(): array
    {
        $user = User::factory()->create([
            'identity_provider' => 'test',
            'provider_subject' => 'payment-owner-'.uniqid(),
            'email' => 'payment-'.uniqid().'@example.com',
            'display_name' => 'Payment Test User',
            'status' => 'active',
        ]);

        $financialYear = FinancialYear::query()->create([
            'user_id' => $user->id,
            'year_code' => '2026-27',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'status' => 'active',
        ]);

        $documentType = DocumentType::query()->create([
            'code' => 'PAYMENT_TEST',
            'name' => 'Payment Test Document',
            'status' => 'active',
            'sort_order' => 1,
        ]);

        return [$user, $financialYear, $documentType];
    }

    private function createDocument(
        User $user,
        ?FinancialYear $financialYear,
        DocumentType $documentType,
    ): Document {
        return Document::query()->create([
            'user_id' => $user->id,
            'financial_year_id' => $financialYear?->id,
            'document_type_id' => $documentType->id,
            'storage_disk' => 'documents',
            'object_key' => 'payment/'.uniqid().'.pdf',
            'content_sha256' => hash('sha256', uniqid()),
            'original_filename' => 'strata-notice.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
            'uploaded_at' => now(),
            'status' => 'review_required',
        ]);
    }
}
