<?php

namespace App\Application\Properties;

use App\Domain\Properties\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateProperty
{
    public function execute(
        User $user,
        ?string $referenceCode = null,
        ?string $addressLine1 = null,
        ?string $addressLine2 = null,
        ?string $suburb = null,
        ?string $state = null,
        ?string $postcode = null,
        string $countryCode = 'AU',
        ?array $locationMetadata = null,
    ): Property {
        return DB::transaction(function () use (
            $user,
            $referenceCode,
            $addressLine1,
            $addressLine2,
            $suburb,
            $state,
            $postcode,
            $countryCode,
            $locationMetadata,
        ): Property {
            return Property::create([
                'user_id' => $user->getKey(),
                'reference_code' => $referenceCode,
                'address_line_1' => $addressLine1,
                'address_line_2' => $addressLine2,
                'suburb' => $suburb,
                'state' => $state,
                'postcode' => $postcode,
                'country_code' => $countryCode,
                'location_metadata' => $locationMetadata,
            ]);
        });
    }
}
