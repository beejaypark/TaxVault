<?php

namespace App\Application\OCR\Validation;

use App\Application\OCR\Data\NormalizedExtraction;
use DateTimeImmutable;
use InvalidArgumentException;

final class NormalizedExtractionValidator
{
    public function validate(
        NormalizedExtraction $extraction
    ): NormalizedExtraction {
        if (trim($extraction->schemaVersion) === '') {
            throw new InvalidArgumentException(
                'Normalized extraction schema version is required.'
            );
        }

        foreach ($extraction->fields as $fieldPath => $field) {
            if (!is_string($fieldPath) || trim($fieldPath) === '') {
                throw new InvalidArgumentException(
                    'Normalized extraction field path must be a non-empty string.'
                );
            }

            if (!is_array($field)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Normalized extraction field "%s" must be an array.',
                        $fieldPath
                    )
                );
            }

            if (!array_key_exists('value', $field)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Normalized extraction field "%s" requires value.',
                        $fieldPath
                    )
                );
            }

            if (!array_key_exists('confidence', $field)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Normalized extraction field "%s" requires confidence.',
                        $fieldPath
                    )
                );
            }

            if (
                !is_int($field['confidence']) &&
                !is_float($field['confidence'])
            ) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Normalized extraction field "%s" confidence must be numeric.',
                        $fieldPath
                    )
                );
            }

            if (
                $field['confidence'] < 0 ||
                $field['confidence'] > 100
            ) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Normalized extraction field "%s" confidence must be between 0 and 100.',
                        $fieldPath
                    )
                );
            }

            if (
                !array_key_exists('source', $field) ||
                !is_string($field['source']) ||
                trim($field['source']) === ''
            ) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Normalized extraction field "%s" requires a non-empty source.',
                        $fieldPath
                    )
                );
            }

            $this->validateDateField($fieldPath, $field['value']);
        }

        return $extraction;
    }

    private function validateDateField(string $fieldPath, mixed $value): void
    {
        if (! str_ends_with($fieldPath, '_date') && ! str_ends_with($fieldPath, '.date')) {
            return;
        }

        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            throw new InvalidArgumentException(
                sprintf('Normalized extraction date field "%s" must use YYYY-MM-DD.', $fieldPath),
            );
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        $year = (int) substr($value, 0, 4);

        if (
            $date === false ||
            ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) ||
            $year < 1900 ||
            $year > 2100
        ) {
            throw new InvalidArgumentException(
                sprintf('Normalized extraction date field "%s" is invalid: %s.', $fieldPath, $value),
            );
        }
    }
}
