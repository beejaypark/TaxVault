<?php

namespace App\Http\Controllers\Api;

use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Properties\Models\MortgageInterestEntry;
use App\Domain\Properties\Models\Property;
use App\Domain\Transactions\AtoCategory;
use App\Domain\Transactions\Models\Transaction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RentalSummaryController extends Controller
{
    public function show(Request $request, string $property): JsonResponse
    {
        $userId = $request->user()->getKey();

        $model = Property::query()
            ->whereKey($property)
            ->where('user_id', $userId)
            ->firstOrFail();

        $request->validate(['financial_year_id' => ['required', 'uuid']]);

        $year = FinancialYear::query()
            ->whereKey($request->query('financial_year_id'))
            ->where('user_id', $userId)
            ->firstOrFail();

        $catalog = AtoCategory::all();

        $rows = Transaction::query()
            ->where('user_id', $userId)
            ->where('property_id', $model->getKey())
            ->where('financial_year_id', $year->getKey())
            ->where('ato_category', 'like', 'rental\_%')
            ->selectRaw('ato_category, sum(amount) as total, count(*) as records')
            ->groupBy('ato_category')
            ->get();

        $income = [];
        $expenses = [];

        foreach ($rows as $row) {
            $code = (string) $row->ato_category;
            $definition = $catalog[$code] ?? null;

            if ($definition === null) {
                continue;
            }

            $line = [
                'category' => $code,
                'label' => $definition['label'],
                'amount' => round((float) $row->total, 2),
                'records' => (int) $row->records,
            ];

            if ($definition['direction'] === 'income') {
                $income[] = $line;
            } else {
                $expenses[] = $line;
            }
        }

        $mortgageInterest = round((float) MortgageInterestEntry::query()
            ->whereIn('mortgage_id', $model->mortgages()->pluck('mortgages.id'))
            ->whereBetween('month', [$year->start_date->toDateString(), $year->end_date->toDateString()])
            ->sum('interest_amount'), 2);

        $recordedInterest = 0.0;
        foreach ($expenses as $line) {
            if ($line['category'] === AtoCategory::RENTAL_INTEREST) {
                $recordedInterest = $line['amount'];
            }
        }

        // Loan records fill the interest line only when no interest transaction is recorded.
        if ($recordedInterest === 0.0 && $mortgageInterest > 0) {
            $expenses[] = [
                'category' => AtoCategory::RENTAL_INTEREST,
                'label' => $catalog[AtoCategory::RENTAL_INTEREST]['label'],
                'amount' => $mortgageInterest,
                'records' => 0,
                'source' => 'mortgage_records',
            ];
        }

        $totalIncome = round(array_sum(array_column($income, 'amount')), 2);
        $totalExpenses = round(array_sum(array_column($expenses, 'amount')), 2);

        return response()->json(['data' => [
            'property_id' => $model->getKey(),
            'financial_year_id' => $year->getKey(),
            'income' => $income,
            'expenses' => $expenses,
            'total_income' => $totalIncome,
            'total_expenses' => $totalExpenses,
            'net_rental_result' => round($totalIncome - $totalExpenses, 2),
            'interest_from_mortgage_records' => $mortgageInterest,
            'interest_from_transactions' => $recordedInterest,
            'interest_mismatch' => $recordedInterest > 0
                && $mortgageInterest > 0
                && abs($recordedInterest - $mortgageInterest) >= 1,
        ]]);
    }
}
