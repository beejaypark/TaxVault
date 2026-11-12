<?php

namespace App\Http\Controllers\Api;

use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Properties\Models\Mortgage;
use App\Domain\Properties\Models\MortgageInterestEntry;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MortgageInterestImportController extends Controller
{
    public function preview(Request $request, string $financialYear): JsonResponse
    {
        $year = $this->ownedFinancialYear($request, $financialYear);
        $rows = $request->validate([
            'rows' => ['required', 'array', 'min:1', 'max:100'],
            'rows.*.bank_name' => ['required', 'string', 'max:100'],
            'rows.*.amount' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'rows.*.label' => ['nullable', 'string', 'max:255'],
        ])['rows'];

        return response()->json([
            'data' => array_map(
                fn (array $row): array => $this->previewRow($request, $year, $row),
                $rows,
            ),
        ]);
    }

    public function store(Request $request, string $financialYear): JsonResponse
    {
        $year = $this->ownedFinancialYear($request, $financialYear);
        $items = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.mortgage_id' => ['required', 'uuid'],
            'items.*.amount' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'items.*.month' => ['nullable', 'date_format:Y-m'],
        ])['items'];

        $imported = DB::transaction(function () use ($request, $year, $items): array {
            $result = [];
            $defaultMonth = $year->end_date->copy()->startOfMonth()->toDateString();

            foreach ($items as $item) {
                $mortgage = Mortgage::query()
                    ->with('property:id,reference_code,address_line_1')
                    ->where('user_id', $request->user()->getKey())
                    ->whereKey($item['mortgage_id'])
                    ->where(function ($query) use ($year): void {
                        $query->whereNull('start_date')->orWhereDate('start_date', '<=', $year->end_date);
                    })
                    ->where(function ($query) use ($year): void {
                        $query->whereNull('maturity_date')->orWhereDate('maturity_date', '>=', $year->start_date);
                    })
                    ->firstOrFail();

                $month = ($item['month'] ?? null) !== null
                    ? $item['month'].'-01'
                    : $defaultMonth;

                $monthDate = CarbonImmutable::parse($month);
                if ($monthDate->lt($year->start_date->startOfMonth()) || $monthDate->gt($year->end_date->startOfMonth())) {
                    throw ValidationException::withMessages([
                        'items' => sprintf('Interest month %s must be within FY %s to %s.', $item['month'] ?? $month, $year->start_date->toDateString(), $year->end_date->toDateString()),
                    ]);
                }

                $entry = MortgageInterestEntry::query()->updateOrCreate(
                    ['mortgage_id' => $mortgage->getKey(), 'month' => $month],
                    ['interest_amount' => $item['amount']],
                );

                $result[] = [
                    'id' => $entry->getKey(),
                    'mortgage_id' => $mortgage->getKey(),
                    'property_id' => $mortgage->property_id,
                    'property_reference_code' => $mortgage->property?->reference_code,
                    'bank_name' => $mortgage->bank_name,
                    'month' => $entry->month,
                    'interest_amount' => $entry->interest_amount,
                ];
            }

            return $result;
        });

        return response()->json(['data' => $imported], 201);
    }

    /** @return array<string, mixed> */
    private function previewRow(Request $request, FinancialYear $year, array $row): array
    {
        $mortgages = Mortgage::query()
            ->with('property:id,reference_code,address_line_1')
            ->where('user_id', $request->user()->getKey())
            ->whereRaw('LOWER(bank_name) = ?', [strtolower(trim($row['bank_name']))])
            ->where(function ($query) use ($year): void {
                $query->whereNull('start_date')->orWhereDate('start_date', '<=', $year->end_date);
            })
            ->where(function ($query) use ($year): void {
                $query->whereNull('maturity_date')->orWhereDate('maturity_date', '>=', $year->start_date);
            })
            ->orderBy('start_date')
            ->get();

        return [
            'label' => $row['label'] ?? null,
            'bank_name' => $row['bank_name'],
            'amount' => $row['amount'],
            'match_status' => $mortgages->count() === 1 ? 'matched' : ($mortgages->isEmpty() ? 'unmatched' : 'ambiguous'),
            'mortgage_id' => $mortgages->count() === 1 ? $mortgages->first()->getKey() : null,
            'candidates' => $mortgages->map(fn (Mortgage $mortgage): array => [
                'mortgage_id' => $mortgage->getKey(),
                'bank_name' => $mortgage->bank_name,
                'property_id' => $mortgage->property_id,
                'property_reference_code' => $mortgage->property?->reference_code,
                'property_address' => $mortgage->property?->address_line_1,
                'start_date' => $mortgage->start_date,
                'maturity_date' => $mortgage->maturity_date,
            ])->values()->all(),
        ];
    }

    private function ownedFinancialYear(Request $request, string $id): FinancialYear
    {
        return FinancialYear::query()
            ->where('user_id', $request->user()->getKey())
            ->whereKey($id)
            ->firstOrFail();
    }
}
