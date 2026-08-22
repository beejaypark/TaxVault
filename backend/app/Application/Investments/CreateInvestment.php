<?php

namespace App\Application\Investments;

use App\Domain\Investments\Models\Investment;
use App\Domain\Properties\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class CreateInvestment
{
    public function execute(
        User $user,
        string $investmentType,
        string $acquisitionDate,
        ?string $disposalDate = null,
        ?string $propertyId = null,
        ?string $quantity = null,
        ?string $ownershipPercentage = null,
        ?string $costBase = null,
        ?string $incidentalCosts = null,
        ?string $proceeds = null,
        ?string $sourceSystem = null,
        ?string $externalId = null,
        ?array $metadata = null,
    ): Investment {
        return DB::transaction(function () use (
            $user,
            $investmentType,
            $acquisitionDate,
            $disposalDate,
            $propertyId,
            $quantity,
            $ownershipPercentage,
            $costBase,
            $incidentalCosts,
            $proceeds,
            $sourceSystem,
            $externalId,
            $metadata,
        ): Investment {
            if ($propertyId !== null) {
                $property = Property::query()
                    ->whereKey($propertyId)
                    ->where('user_id', $user->getKey())
                    ->first();

                if ($property === null) {
                    throw new LogicException(
                        'Property does not belong to the authenticated user.'
                    );
                }
            }

            return Investment::create([
                'user_id' => $user->getKey(),
                'property_id' => $propertyId,
                'investment_type' => $investmentType,
                'acquisition_date' => $acquisitionDate,
                'disposal_date' => $disposalDate,
                'quantity' => $quantity,
                'ownership_percentage' => $ownershipPercentage,
                'cost_base' => $costBase,
                'incidental_costs' => $incidentalCosts,
                'proceeds' => $proceeds,
                'source_system' => $sourceSystem,
                'external_id' => $externalId,
                'metadata' => $metadata,
            ]);
        });
    }
}
