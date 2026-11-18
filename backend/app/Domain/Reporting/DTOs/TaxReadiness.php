<?php

namespace App\Domain\Reporting\DTOs;

final readonly class TaxReadiness
{
    /**
     * @param  array<string, array{label: string, completed: int, total: int, percentage: int|null, applicable: bool}>  $components
     * @param  list<array{severity: string, code: string, count: int, message: string}>  $issues
     */
    public function __construct(
        public string $financialYearId,
        public string $financialYearCode,
        public ?int $score,
        public string $status,
        public string $calculationVersion,
        public array $components,
        public array $issues,
    ) {}

    public function toArray(): array
    {
        return [
            'financial_year_id' => $this->financialYearId,
            'financial_year_code' => $this->financialYearCode,
            'score' => $this->score,
            'status' => $this->status,
            'calculation_version' => $this->calculationVersion,
            'components' => $this->components,
            'issues' => $this->issues,
        ];
    }
}
