<?php

namespace App\Application\Reviews\Services;

use App\Domain\Evidence\Models\TaxEvidence;
use App\Domain\Reviews\Models\Review;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateAuthoritativeCorrection
{
    public function execute(
        Review $review,
        string $fieldPath,
        mixed $value,
    ): TaxEvidence {
        $review->loadMissing([
            'document',
            'extraction',
        ]);

        if ($review->document_extraction_id === null) {
            throw ValidationException::withMessages([
                'review' => 'The review does not reference a document extraction.',
            ]);
        }

        if ($review->document_id === null) {
            throw ValidationException::withMessages([
                'review' => 'The review does not reference a document.',
            ]);
        }

        if ($review->status !== 'pending') {
            throw ValidationException::withMessages([
                'review' => 'Only pending reviews can be corrected.',
            ]);
        }

        if ($review->document === null) {
            throw ValidationException::withMessages([
                'review' => 'The referenced document was not found.',
            ]);
        }

        if ($review->extraction === null) {
            throw ValidationException::withMessages([
                'review' => 'The referenced document extraction was not found.',
            ]);
        }

        $normalizedFields = data_get(
            $review->extraction->output,
            'normalized.fields',
            []
        );

        if (!is_array($normalizedFields)) {
            throw ValidationException::withMessages([
                'field_path' => 'The normalized extraction fields are invalid.',
            ]);
        }

        if (!array_key_exists($fieldPath, $normalizedFields)) {
            throw ValidationException::withMessages([
                'field_path' => 'The specified extraction field does not exist.',
            ]);
        }

        return DB::transaction(function () use (
            $review,
            $fieldPath,
            $value,
        ): TaxEvidence {
            TaxEvidence::query()
                ->where(
                    'user_id',
                    $review->reviewer_id
                )
                ->where(
                    'financial_year_id',
                    $review->document->financial_year_id
                )
                ->where(
                    'document_id',
                    $review->document_id
                )
                ->where(
                    'extraction_id',
                    $review->document_extraction_id
                )
                ->where(
                    'evidence_type',
                    'review_correction'
                )
                ->where(
                    'field_path',
                    $fieldPath
                )
                ->where(
                    'status',
                    'active'
                )
                ->update([
                    'status' => 'superseded',
                ]);

            return TaxEvidence::query()->create([
                'user_id' => $review->reviewer_id,
                'financial_year_id' =>
                    $review->document->financial_year_id,
                'document_id' => $review->document_id,
                'extraction_id' =>
                    $review->document_extraction_id,
                'evidence_type' => 'review_correction',
                'source_type' => 'document_extraction',
                'source_id' =>
                    $review->document_extraction_id,
                'field_path' => $fieldPath,
                'extracted_value' => [
                    'value' => $value,
                    'source' => 'reviewer',
                ],
                'status' => 'active',
                'verification_status' => 'verified',
                'confidence' => 100.00,
                'verified_at' => now(),
            ]);
        });
    }
}
