<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Deductions\Models\WorkFromHomeLog;
use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Properties\Models\MortgageInterestEntry;
use App\Domain\Properties\Models\Property;
use App\Domain\Transactions\AtoCategory;
use App\Domain\Transactions\Models\Transaction;

final class ReturnSummaryBuilder
{
    public const DEFAULT_WFH_RATE = 0.70;

    /** @return array<string, mixed> */
    public function build(string $userId, FinancialYear $year): array
    {
        $catalog = AtoCategory::all();
        $properties = Property::query()->where('user_id', $userId)->get()->keyBy('id');

        $rows = Transaction::query()
            ->where('user_id', $userId)
            ->where('financial_year_id', $year->getKey())
            ->selectRaw('ato_category, property_id, direction, sum(amount) as total, count(*) as records')
            ->groupBy('ato_category', 'property_id', 'direction')
            ->get();

        $lines = [];
        $uncategorised = [
            'income' => ['amount' => 0.0, 'records' => 0],
            'expense' => ['amount' => 0.0, 'records' => 0],
        ];

        foreach ($rows as $row) {
            $code = $row->ato_category;
            $definition = $code ? ($catalog[$code] ?? null) : null;

            if ($definition === null) {
                $uncategorised[$row->direction]['amount'] += (float) $row->total;
                $uncategorised[$row->direction]['records'] += (int) $row->records;

                continue;
            }

            $lines[] = $this->line($code, $definition, (float) $row->total, (int) $row->records, $row->property_id, $properties);
        }

        $wfh = $this->workFromHome($userId, $year);

        if ($wfh['amount'] > 0) {
            $lines[] = $this->line('work_from_home', $catalog['work_from_home'], $wfh['amount'], $wfh['days'], null, $properties, 'wfh_log');
        }

        $rental = $this->rentalByProperty($userId, $year, $lines, $properties);

        usort($lines, fn (array $a, array $b): int => [$a['group_order'], $a['ato_ref'], $a['label'], $a['property_name']]
            <=> [$b['group_order'], $b['ato_ref'], $b['label'], $b['property_name']]);

        $deductions = array_sum(array_column(array_filter($lines, fn ($l) => $l['group'] === 'Deductions'), 'amount'));
        $income = array_sum(array_column(array_filter($lines, fn ($l) => $l['group'] === 'Income'), 'amount'));

        foreach ($uncategorised as &$bucket) {
            $bucket['amount'] = round($bucket['amount'], 2);
        }
        unset($bucket);

        return [
            'financial_year_id' => $year->getKey(),
            'year_code' => $year->year_code,
            'start_date' => $year->start_date->toDateString(),
            'lines' => $lines,
            'rental' => $rental,
            'work_from_home' => $wfh,
            'uncategorised' => $uncategorised,
            'totals' => [
                'income' => round($income, 2),
                'deductions' => round($deductions, 2),
                'net_rental_result' => round(array_sum(array_column($rental, 'net_result')), 2),
            ],
        ];
    }

    /** @return array{hours: float, days: int, rate: float, amount: float, rate_is_default: bool} */
    public function workFromHome(string $userId, FinancialYear $year): array
    {
        $logs = WorkFromHomeLog::query()
            ->where('user_id', $userId)
            ->whereBetween('work_date', [$year->start_date->toDateString(), $year->end_date->toDateString()]);

        $hours = (float) (clone $logs)->sum('hours');
        $days = (clone $logs)->count();
        $rate = $year->wfh_rate !== null ? (float) $year->wfh_rate : self::DEFAULT_WFH_RATE;

        return [
            'hours' => round($hours, 2),
            'days' => $days,
            'rate' => $rate,
            'amount' => round($hours * $rate, 2),
            'rate_is_default' => $year->wfh_rate === null,
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function line(string $code, array $definition, float $amount, int $records, ?string $propertyId, $properties, ?string $source = null): array
    {
        $property = $propertyId ? $properties->get($propertyId) : null;
        $groupOrder = ['Income' => 1, 'Rental property' => 2, 'Deductions' => 3][$definition['group']] ?? 9;

        return [
            'category' => $code,
            'label' => $definition['label'],
            'group' => $definition['group'],
            'group_order' => $groupOrder,
            'ato_ref' => $definition['ato_ref'],
            'direction' => $definition['direction'],
            'amount' => round($amount, 2),
            'records' => $records,
            'property_id' => $propertyId,
            'property_name' => $property ? ($property->reference_code ?: $property->address_line_1) : '',
            'source' => $source,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    private function rentalByProperty(string $userId, FinancialYear $year, array $lines, $properties): array
    {
        $result = [];

        foreach ($properties as $property) {
            $own = array_filter($lines, fn ($l) => $l['property_id'] === $property->getKey() && AtoCategory::isRental($l['category']));
            $income = array_sum(array_column(array_filter($own, fn ($l) => $l['direction'] === 'income'), 'amount'));
            $expenses = array_sum(array_column(array_filter($own, fn ($l) => $l['direction'] === 'expense'), 'amount'));

            $interestRecorded = array_sum(array_column(array_filter($own, fn ($l) => $l['category'] === AtoCategory::RENTAL_INTEREST), 'amount'));
            $mortgageInterest = (float) MortgageInterestEntry::query()
                ->whereIn('mortgage_id', $property->mortgages()->pluck('mortgages.id'))
                ->whereBetween('month', [$year->start_date->toDateString(), $year->end_date->toDateString()])
                ->sum('interest_amount');

            $interestFallback = $interestRecorded == 0 && $mortgageInterest > 0 ? $mortgageInterest : 0.0;

            if (count($own) === 0 && $interestFallback == 0.0) {
                continue;
            }

            $result[] = [
                'property_id' => $property->getKey(),
                'property_name' => $property->reference_code ?: $property->address_line_1,
                'income' => round($income, 2),
                'expenses' => round($expenses + $interestFallback, 2),
                'interest_from_mortgage_records' => round($interestFallback, 2),
                'net_result' => round($income - $expenses - $interestFallback, 2),
            ];
        }

        return $result;
    }
}
