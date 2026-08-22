<?php

namespace App\Application\FinancialYears;

use App\Domain\FinancialYears\Models\FinancialYear;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateFinancialYear
{
    public function execute(
        User $user,
        string $yearCode,
        string $startDate,
        string $endDate,
        string $status = 'active',
    ): FinancialYear {
        return DB::transaction(function () use (
            $user,
            $yearCode,
            $startDate,
            $endDate,
            $status,
        ): FinancialYear {
            return FinancialYear::create([
                'user_id' => $user->getKey(),
                'year_code' => $yearCode,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => $status,
            ]);
        });
    }
}
