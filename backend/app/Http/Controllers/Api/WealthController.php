<?php

namespace App\Http\Controllers\Api;

use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Investments\Models\Investment;
use App\Domain\Properties\Models\Mortgage;
use App\Domain\Properties\Models\Property;
use App\Domain\Transactions\Models\Transaction;
use App\Domain\Wealth\Models\AssetValuation;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WealthController extends Controller
{
    private const MONTHS = 12;

    public function index(Request $request): JsonResponse
    {
        $userId = (string) $request->user()->getKey();
        $today = CarbonImmutable::today();
        $year = $request->filled('financial_year_id')
            ? FinancialYear::query()->where('user_id', $userId)->find($request->input('financial_year_id'))
            : null;
        $yearEnd = $year ? CarbonImmutable::parse($year->end_date) : null;
        // Past years are valued as at their end date; the current year uses today's values.
        $asOf = $yearEnd !== null && $yearEnd->lessThan($today) ? $yearEnd : $today;
        $historical = $asOf->lessThan($today);
        $latestAt = fn (string $kind, string $id): ?AssetValuation => AssetValuation::query()
            ->where($kind, $id)->where('as_of', '<=', $asOf->toDateString())->orderByDesc('as_of')->first();

        $properties = Property::query()->where('user_id', $userId)->get();
        $investments = Investment::query()->where('user_id', $userId)->whereNull('disposal_date')->get();
        $mortgages = Mortgage::query()->where('user_id', $userId)->get();

        $assets = [];
        foreach ($properties as $property) {
            $assets[] = [
                'kind' => 'property',
                'id' => $property->getKey(),
                'label' => trim($property->address_line_1.' '.$property->suburb),
                'value' => $historical ? ($v = $latestAt('property_id', $property->getKey())) ? (float) $v->value : null : ($property->current_value !== null ? (float) $property->current_value : null),
                'as_of' => $historical ? $latestAt('property_id', $property->getKey())?->as_of->toDateString() : $property->current_value_date,
            ];
        }
        foreach ($investments as $investment) {
            $past = $historical ? $latestAt('investment_id', $investment->getKey()) : null;
            $hasValue = $historical ? $past !== null : $investment->current_value !== null;
            $current = $historical ? ($past?->value) : $investment->current_value;
            $assets[] = [
                'kind' => 'investment',
                'id' => $investment->getKey(),
                'label' => $investment->investment_type,
                'value' => $hasValue ? (float) $current : ($investment->cost_base !== null ? (float) $investment->cost_base : null),
                'as_of' => $hasValue ? ($historical ? $past->as_of->toDateString() : $investment->current_value_date) : null,
                'is_cost_base' => ! $hasValue,
            ];
        }

        $totalAssets = array_sum(array_map(static fn (array $a): float => $a['value'] ?? 0.0, $assets));
        $liabilities = (float) $mortgages->sum('loan_amount');
        $weightedRate = $liabilities > 0
            ? $mortgages->sum(static fn ($m): float => (float) $m->loan_amount * (float) ($m->interest_rate ?? 0)) / $liabilities
            : 0.0;

        return response()->json(['data' => [
            'assets' => $assets,
            'total_assets' => round($totalAssets, 2),
            'total_liabilities' => round($liabilities, 2),
            'net_worth' => round($totalAssets - $liabilities, 2),
            'average_interest_rate' => round($weightedRate, 2),
            'history' => $this->history($userId, $asOf, $liabilities),
            'cash_flow' => $this->cashFlow($userId, $year ? CarbonImmutable::parse($year->start_date)->startOfMonth() : $today->subMonthsNoOverflow(self::MONTHS - 1)->startOfMonth()),
        ]]);
    }

    /** @return list<array{month: string, assets: float, liabilities: float, net_worth: float}> */
    private function history(string $userId, CarbonImmutable $today, float $liabilities): array
    {
        $valuations = AssetValuation::query()->where('user_id', $userId)->orderBy('as_of')->get();
        $points = [];

        for ($i = self::MONTHS - 1; $i >= 0; $i--) {
            $end = $today->subMonthsNoOverflow($i)->endOfMonth();
            if ($end->greaterThan($today)) {
                $end = $today;
            }

            $latest = [];
            foreach ($valuations as $valuation) {
                if ($valuation->as_of->toDateString() <= $end->toDateString()) {
                    $latest[$valuation->property_id ?? $valuation->investment_id] = (float) $valuation->value;
                }
            }
            if ($latest === []) {
                continue;
            }

            $assets = array_sum($latest);
            $points[] = [
                'month' => $end->format('Y-m'),
                'assets' => round($assets, 2),
                'liabilities' => round($liabilities, 2),
                'net_worth' => round($assets - $liabilities, 2),
            ];
        }

        return $points;
    }

    /** @return list<array{month: string, income: float, expenses: float}> */
    private function cashFlow(string $userId, CarbonImmutable $start): array
    {
        $rows = Transaction::query()
            ->where('user_id', $userId)
            ->whereBetween('transaction_date', [$start->toDateString(), $start->addMonthsNoOverflow(self::MONTHS - 1)->endOfMonth()->toDateString()])
            ->get(['transaction_date', 'direction', 'amount']);

        $months = [];
        for ($i = 0; $i < self::MONTHS; $i++) {
            $months[$start->addMonthsNoOverflow($i)->format('Y-m')] = ['income' => 0.0, 'expenses' => 0.0];
        }
        foreach ($rows as $row) {
            $key = $row->transaction_date->format('Y-m');
            if (! isset($months[$key])) {
                continue;
            }
            $field = $row->direction === 'income' ? 'income' : 'expenses';
            $months[$key][$field] += (float) $row->amount;
        }

        $out = [];
        foreach ($months as $month => $values) {
            $out[] = ['month' => $month, 'income' => round($values['income'], 2), 'expenses' => round($values['expenses'], 2)];
        }

        return $out;
    }
}
