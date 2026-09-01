<?php

namespace App\Application\OCR\Services;

use App\Application\OCR\Confidence\ConfidenceEvaluator;
use App\Application\OCR\Contracts\OcrProvider;
use App\Application\OCR\Data\NormalizedExtraction;
use App\Application\OCR\Data\OcrRequest;
use App\Application\OCR\Validation\NormalizedExtractionValidator;
use App\Application\Reviews\Services\CreateConfidenceReview;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentExtraction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProcessDocumentOcr
{
    private readonly NormalizedExtractionValidator $validator;

    public function __construct(
        private readonly OcrProvider $provider,
        ?NormalizedExtractionValidator $validator = null,
        private readonly ConfidenceEvaluator $confidenceEvaluator = new ConfidenceEvaluator(),
        private readonly CreateConfidenceReview $createConfidenceReview = new CreateConfidenceReview(),
    ) {
        $this->validator = $validator
            ?? new NormalizedExtractionValidator();
    }

    public function execute(Document $document): DocumentExtraction
    {
        $request = new OcrRequest(
            documentId: $document->getKey(),
            storageDisk: $document->storage_disk,
            objectKey: $document->object_key,
            mimeType: $document->mime_type,
            originalFilename: $document->original_filename,
            contentSha256: $document->content_sha256,
            metadata: [
                'financial_year_id' => $document->financial_year_id,
                'document_type_id' => $document->document_type_id,
            ],
        );

        $startedAt = now();

        $result = $this->provider->process($request);

        $qualityMetadata = $result->qualityMetadata;

        if ($result->status === 'completed') {
            $normalized = $result->output['normalized'] ?? null;

            $normalizedExtraction = new NormalizedExtraction(
                schemaVersion: is_array($normalized)
                    ? ($normalized['schema_version'] ?? '')
                    : '',
                fields: is_array($normalized)
                    ? ($normalized['fields'] ?? [])
                    : [],
            );

            $this->validator->validate($normalizedExtraction);

            $confidenceEvaluation = $this->confidenceEvaluator->evaluate(
                $normalizedExtraction
            );

            $qualityMetadata['confidence_evaluation'] =
                $confidenceEvaluation->toArray();

            $qualityMetadata['review_required'] =
                $confidenceEvaluation->requiresReview();
        }

        return DB::transaction(function () use (
            $document,
            $result,
            $startedAt,
            $qualityMetadata,
        ): DocumentExtraction {
            $extraction = DocumentExtraction::query()->create([
                'document_id' => $document->getKey(),
                'provider' => $result->provider,
                'model' => $result->model,
                'model_version' => $result->modelVersion,
                'extraction_version' => $result->extractionVersion,
                'status' => $result->status,
                'correlation_id' => (string) Str::uuid(),
                'started_at' => $startedAt,
                'completed_at' => now(),
                'output' => $result->output,
                'quality_metadata' => $qualityMetadata,
                'error_metadata' => $result->errorMetadata ?: null,
            ]);

            if (
                $result->status === 'completed'
                && ($qualityMetadata['review_required'] ?? false) === true
            ) {
                $normalized = $result->output['normalized'] ?? null;

                $normalizedExtraction = new NormalizedExtraction(
                    schemaVersion: is_array($normalized)
                        ? ($normalized['schema_version'] ?? '')
                        : '',
                    fields: is_array($normalized)
                        ? ($normalized['fields'] ?? [])
                        : [],
                );

                $confidenceEvaluation = $this->confidenceEvaluator->evaluate(
                    $normalizedExtraction
                );

                $this->createConfidenceReview->execute(
                    $document,
                    $extraction,
                    $confidenceEvaluation,
                );
            }

            return $extraction;
        });
    }
}


