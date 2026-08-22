<?php

namespace App\Application\Properties;

use App\Domain\Properties\Models\Property;
use App\Domain\Properties\Models\PropertyPeriod;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class CreatePropertyPeriod
{
    public function execute(
        User $user,
        Property $property,
        string $periodStart,
        ?string $periodEnd,
        string $useType,
        ?string $ownershipPercentage = null,
        ?array $provenance = null,
    ): PropertyPeriod {
        if ($property->user_id !== $user->getKey()) {
            throw new LogicException(
                'Property does not belong to the authenticated user.'
            );
        }

        return DB::transaction(function () use (
            $property,
            $periodStart,
            $periodEnd,
            $useType,
            $ownershipPercentage,
            $provenance,
        ): PropertyPeriod {
            return PropertyPeriod::create([
                'property_id' => $property->getKey(),
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'use_type' => $useType,
                'ownership_percentage' => $ownershipPercentage,
                'provenance' => $provenance,
            ]);
        });
    }
}
