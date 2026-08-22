<?php

namespace App\Domain\Reporting\DTOs;

final readonly class TaxYearCompleteness
{
    public function __construct(
        public string $financialYearId,
        public string $financialYearCode,

        public int $incomeRecords,
        public int $expenseRecords,

        public int $transactionRecords,

        public int $evidenceLinked,
        public int $evidenceMissing,

        public int $verifiedRecords,
        public int $unverifiedRecords,

        public int $propertyPeriods,

        public float $incomeAmount,
        public float $expenseAmount,

        public array $gaps,
    ) {}

    public function toArray(): array
    {
        return [
            'financial_year_id' => $this->financialYearId,
            'financial_year_code' => $this->financialYearCode,

            'income' => [
                'records' => $this->incomeRecords,
                'amount' => $this->incomeAmount,
            ],

            'expenses' => [
                'records' => $this->expenseRecords,
                'amount' => $this->expenseAmount,
            ],

            'transactions' => [
                'records' => $this->transactionRecords,
            ],

            'evidence' => [
                'linked' => $this->evidenceLinked,
                'missing' => $this->evidenceMissing,
            ],

            'verification' => [
                'verified' => $this->verifiedRecords,
                'unverified' => $this->unverifiedRecords,
            ],

            'property' => [
                'periods' => $this->propertyPeriods,
            ],

            'gaps' => $this->gaps,
        ];
    }
}
