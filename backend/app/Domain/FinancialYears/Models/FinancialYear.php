<?php

namespace App\Domain\FinancialYears\Models;

use App\Domain\Shared\Models\UuidModel;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * @property string $id
 * @property string $user_id
 * @property string $year_code
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property string $status
 */
class FinancialYear extends UuidModel
{
    protected $fillable = [
        'user_id',
        'year_code',
        'start_date',
        'end_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $financialYear): void {
            self::assertCanonicalAustralianPeriod(
                $financialYear->year_code,
                $financialYear->start_date,
                $financialYear->end_date,
            );
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function assertCanonicalAustralianPeriod(
        string $yearCode,
        CarbonInterface $startDate,
        CarbonInterface $endDate,
    ): void {
        if (! preg_match('/^(\d{4})-(\d{2})$/', $yearCode, $matches)) {
            throw new InvalidArgumentException('Financial year code must use the YYYY-YY format.');
        }

        $startYear = (int) $matches[1];
        $endYearSuffix = (int) $matches[2];

        if (
            $endYearSuffix !== ($startYear + 1) % 100
            || ! $startDate->isSameDay("{$startYear}-07-01")
            || ! $endDate->isSameDay(($startYear + 1).'-06-30')
        ) {
            throw new InvalidArgumentException(
                'Financial year dates must match the Australian 1 July to 30 June period.'
            );
        }
    }
}
