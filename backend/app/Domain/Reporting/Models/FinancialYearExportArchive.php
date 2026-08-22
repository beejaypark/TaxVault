<?php

namespace App\Domain\Reporting\Models;

use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Shared\Models\UuidModel;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $user_id
 * @property string $financial_year_id
 * @property string $export_version
 * @property string $sha256
 * @property CarbonImmutable $generated_at
 * @property array<string, mixed> $payload
 */
final class FinancialYearExportArchive extends UuidModel
{
    protected $table = 'financial_year_export_archives';

    protected $fillable = [
        'user_id',
        'financial_year_id',
        'export_version',
        'sha256',
        'generated_at',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'immutable_datetime',
            'payload' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function financialYear(): BelongsTo
    {
        return $this->belongsTo(FinancialYear::class);
    }
}
