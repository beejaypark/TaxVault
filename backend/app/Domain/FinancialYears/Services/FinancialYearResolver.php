<?php

namespace App\Domain\FinancialYears\Services;

use App\Domain\FinancialYears\Models\FinancialYear;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\MultipleRecordsFoundException;

class FinancialYearResolver
{
    /**
     * Resolve the Financial Year for a user and a given date.
     *
     * The Financial Year is determined exclusively from persisted
     * FinancialYear boundaries. No client-side FY calculation is involved.
     *
     * @throws ModelNotFoundException
     * @throws MultipleRecordsFoundException
     */
    public function resolve(string $userId, CarbonInterface|string $date): FinancialYear
    {
        return FinancialYear::query()
            ->where('user_id', $userId)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->sole();
    }
}
