<?php

namespace App\Domain\Properties\Models;

use App\Domain\Shared\Models\UuidModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mortgage extends UuidModel
{
    protected $table = 'mortgages';

    protected $fillable = [
        'user_id',
        'property_id',
        'bank_name',
        'loan_type',
        'repayment_frequency',
        'loan_amount',
        'repayment_amount',
        'interest_rate',
        'start_date',
        'maturity_date',
        'verified_balance',
        'verified_balance_date',
    ];

    protected $with = ['interestEntries', 'rateChanges'];

    protected $appends = ['estimated_balance'];

    protected function casts(): array
    {
        return [
            'loan_amount' => 'decimal:2',
            'repayment_amount' => 'decimal:2',
            'interest_rate' => 'decimal:3',
            'start_date' => 'date:Y-m-d',
            'maturity_date' => 'date:Y-m-d',
            'verified_balance' => 'decimal:2',
            'verified_balance_date' => 'date:Y-m-d',
        ];
    }

    public function getEstimatedBalanceAttribute(): float
    {
        return $this->estimatedBalance(CarbonImmutable::today());
    }

    public function getBalanceVarianceAttribute(): ?float
    {
        return $this->verified_balance === null
            ? null
            : round($this->estimated_balance - (float) $this->verified_balance, 2);
    }

    public function getBalanceNeedsReviewAttribute(): bool
    {
        return $this->balance_variance !== null
            && abs($this->balance_variance) > max(1000, (float) $this->loan_amount * 0.02);
    }

    public function estimatedBalance(CarbonImmutable $asOf): float
    {
        $principal = (float) $this->loan_amount;
        if ($principal <= 0 || $this->loan_type === 'interest_only' || $this->start_date === null || $this->maturity_date === null) {
            return round($principal, 2);
        }

        $start = CarbonImmutable::parse($this->start_date);
        $maturity = CarbonImmutable::parse($this->maturity_date);
        if ($asOf->greaterThanOrEqualTo($maturity)) {
            return 0.0;
        }

        $periodsPerYear = $this->periodsPerYear();
        $verifiedDate = $this->verified_balance !== null && $this->verified_balance_date !== null
            ? CarbonImmutable::parse($this->verified_balance_date)
            : null;
        $anchorDate = $verifiedDate !== null && $verifiedDate->lessThanOrEqualTo($asOf) ? $verifiedDate : $start;
        $balance = $anchorDate === $verifiedDate ? (float) $this->verified_balance : $principal;
        $totalPeriods = max(1, (int) round($anchorDate->diffInDays($maturity) / 365.25 * $periodsPerYear));
        $cursor = $anchorDate;
        $remainingPeriods = $totalPeriods;
        $currentChange = $this->rateChanges
            ->filter(fn (MortgageRateChange $change): bool => CarbonImmutable::parse($change->effective_date)->lessThanOrEqualTo($cursor))
            ->sortByDesc('effective_date')
            ->first();
        $changes = $this->rateChanges
            ->filter(fn (MortgageRateChange $change): bool => CarbonImmutable::parse($change->effective_date)->greaterThan($cursor)
                && CarbonImmutable::parse($change->effective_date)->lessThanOrEqualTo($asOf))
            ->sortBy('effective_date');
        $rate = (float) ($currentChange?->rate ?? $this->interest_rate ?? 0);
        $paymentOverride = $currentChange?->bank_repayment_amount ?? $this->repayment_amount;

        foreach ($changes as $change) {
            $changeDate = CarbonImmutable::parse($change->effective_date);
            $segmentPeriods = min($remainingPeriods, $this->periodsBetween($cursor, $changeDate, $periodsPerYear));
            $balance = $this->amortise($balance, $rate, $segmentPeriods, $remainingPeriods, $paymentOverride, $periodsPerYear);
            $remainingPeriods -= $segmentPeriods;
            $cursor = $changeDate;
            $rate = (float) $change->rate;
            $paymentOverride = $change->bank_repayment_amount;
        }

        $segmentPeriods = min($remainingPeriods, $this->periodsBetween($cursor, $asOf, $periodsPerYear));
        $balance = $this->amortise($balance, $rate, $segmentPeriods, $remainingPeriods, $paymentOverride, $periodsPerYear);

        return round(max(0, $balance), 2);
    }

    private function periodsBetween(CarbonImmutable $from, CarbonImmutable $to, int $periodsPerYear): int
    {
        return max(0, (int) round($from->diffInDays($to) / 365.25 * $periodsPerYear));
    }

    private function amortise(float $balance, float $annualRate, int $periods, int $remainingPeriods, mixed $baselinePayment, int $periodsPerYear): float
    {
        if ($periods <= 0 || $balance <= 0) {
            return $balance;
        }
        $periodicRate = $annualRate / 100 / $periodsPerYear;
        $payment = $baselinePayment !== null && (float) $baselinePayment > 0
            ? (float) $baselinePayment
            : ($periodicRate > 0
                ? $balance * $periodicRate / (1 - ((1 + $periodicRate) ** -max(1, $remainingPeriods)))
                : $balance / max(1, $remainingPeriods));

        if ($periodicRate <= 0) {
            return max(0, $balance - ($payment * $periods));
        }
        $factor = (1 + $periodicRate) ** $periods;

        return max(0, $balance * $factor - $payment * (($factor - 1) / $periodicRate));
    }

    private function periodsPerYear(): int
    {
        return match ($this->repayment_frequency) {
            'weekly' => 52,
            'fortnightly' => 26,
            default => 12,
        };
    }

    public function rateChanges(): HasMany
    {
        return $this->hasMany(MortgageRateChange::class)->orderBy('effective_date');
    }

    /** Keeps the headline rate equal to the latest dated change that has already taken effect. */
    public function syncCurrentRate(): void
    {
        $current = $this->rateChanges()->where('effective_date', '<=', now()->toDateString())->reorder('effective_date', 'desc')->first();

        if ($current !== null) {
            $this->update(['interest_rate' => $current->rate]);
        }
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function interestEntries(): HasMany
    {
        return $this->hasMany(MortgageInterestEntry::class)->orderBy('month');
    }
}
