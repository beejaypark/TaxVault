<?php

namespace App\Http\Controllers\Api;

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

        $properties = Property::query()->where('user_id', $userId)->get();
        $investments = Investment::query()->where('user_id', $userId)->whereNull('disposal_date')->get();
        $mortgages = Mortgage::query()->where('user_id', $userId)->get();

        $assets = [];
        foreach ($properties as $property) {
            $assets[] = [
                'kind' => 'property',
                'id' => $property->getKey(),
                'label' => trim($property->address_line_1.' '.$property->suburb),
                'value' => $property->current_value !== null ? (float) $property->current_value : null,
                'as_of' => $property->current_value_date,
            ];
        }
        foreach ($investments as $investment) {
            $hasValue = $investment->current_value !== null;
            $assets[] = [
                'kind' => 'investment',
                'id' => $investment->getKey(),
                'label' => $investment->investment_type,
                'value' => $hasValue ? (float) $investment->current_value : ($investment->cost_base !== null ? (float) $investment->cost_base : null),
                'as_of' => $hasValue ? $investment->current_value_date : null,
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
            'history' => $this->history($userId, $today, $liabilities),
            'cash_flow' => $this->cashFlow($userId, $today),
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
    private function cashFlow(string $userId, CarbonImmutable $today): array
    {
        $start = $today->subMonthsNoOverflow(self::MONTHS - 1)->startOfMonth();
        $rows = Transaction::query()
            ->where('user_id', $userId)
            ->whereBetween('transaction_date', [$start->toDateString(), $today->endOfMonth()->toDateString()])
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
