<?php

namespace App\Application\OCR\Services;

use App\Application\Reviews\Services\CreateAuthoritativeCorrection;
use App\Application\Reviews\Services\CreateDocumentClassificationReview;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentExtraction;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

final class RepairFailedDocumentExtraction
{
    public function __construct(
        private readonly CreateDocumentClassificationReview $createReview,
        private readonly CreateAuthoritativeCorrection $createCorrection,
    ) {}

    public function execute(Document $document, string $fieldPath, string $value): DocumentExtraction
    {
        if ($fieldPath !== 'invoice_date') {
            throw ValidationException::withMessages([
                'field_path' => 'Only the invoice date can be corrected for a failed extraction.',
            ]);
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $dateErrors = \DateTimeImmutable::getLastErrors();

        if ($date === false || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) || $date->format('Y-m-d') !== $value) {
            throw ValidationException::withMessages([
                'value' => 'Enter a valid date as YYYY-MM-DD.',
            ]);
        }

        $extraction = $document->latestExtraction()->first();

        if ($extraction === null || $extraction->status !== 'failed') {
            throw ValidationException::withMessages([
                'document' => 'Only a failed extraction can be corrected here.',
            ]);
        }

        $output = is_array($extraction->output) ? $extraction->output : [];
        $normalized = is_array($output['normalized'] ?? null) ? $output['normalized'] : [];
        $fields = is_array($normalized['fields'] ?? null) ? $normalized['fields'] : [];
        $originalValue = $fields[$fieldPath]['value'] ?? null;
        $fields[$fieldPath] = array_merge(
            is_array($fields[$fieldPath] ?? null) ? $fields[$fieldPath] : [],
            [
                'value' => $value,
                'confidence' => 100.0,
                'source' => 'manual_correction',
            ],
        );
        $normalized['fields'] = $fields;
        $output['normalized'] = $normalized;

        return DB::transaction(function () use ($document, $extraction, $output, $originalValue, $value): DocumentExtraction {
            $qualityMetadata = is_array($extraction->quality_metadata) ? $extraction->quality_metadata : [];
            $qualityMetadata['manual_corrections'] = array_merge(
                is_array($qualityMetadata['manual_corrections'] ?? null) ? $qualityMetadata['manual_corrections'] : [],
                [[
                    'field_path' => 'invoice_date',
                    'original_value' => $originalValue,
                    'corrected_value' => $value,
                    'corrected_at' => now()->toIso8601String(),
                ]],
            );

            $extraction->update([
                'status' => 'completed',
                'completed_at' => now(),
                'output' => $output,
                'quality_metadata' => $qualityMetadata,
                'error_metadata' => null,
            ]);

            $review = $this->createReview->execute($document, $extraction);
            $document->update(['status' => 'review_required']);
            $this->createCorrection->execute($review, 'invoice_date', $value);

            return $extraction->fresh();
        });
    }
}
