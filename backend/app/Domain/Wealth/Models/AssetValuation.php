<?php

namespace App\Domain\Wealth\Models;

use App\Domain\Shared\Models\UuidModel;

class AssetValuation extends UuidModel
{
    protected $table = 'asset_valuations';

    protected $fillable = ['user_id', 'property_id', 'investment_id', 'value', 'as_of'];

    protected function casts(): array
    {
        return ['as_of' => 'date:Y-m-d', 'value' => 'decimal:2'];
    }

    /** Stores one dated value per asset per day; re-entering the same day overwrites it. */
    public static function record(string $userId, string $kind, string $assetId, mixed $value, mixed $asOf): void
    {
        if ($value === null || $value === '') {
            return;
        }

        static::query()->updateOrCreate(
            [
                'user_id' => $userId,
                $kind => $assetId,
                'as_of' => $asOf ? date('Y-m-d', strtotime((string) $asOf)) : now()->toDateString(),
            ],
            ['value' => $value],
        );
    }
}
