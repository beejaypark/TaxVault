<?php

namespace App\Http\Controllers\Api;

use App\Application\Properties\CreateProperty;
use App\Application\Properties\CreatePropertyPeriod;
use App\Domain\Properties\Models\Property;
use App\Http\Controllers\Controller;
use App\Http\Requests\Properties\StorePropertyPeriodRequest;
use App\Http\Requests\Properties\StorePropertyRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $properties = Property::query()
            ->where('user_id', $request->user()->getKey())
            ->with('periods')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $properties,
        ]);
    }

    public function store(
        StorePropertyRequest $request,
        CreateProperty $createProperty,
    ): JsonResponse {
        $property = $createProperty->execute(
            user: $request->user(),
            referenceCode: $request->input('reference_code'),
            addressLine1: $request->input('address_line_1'),
            addressLine2: $request->input('address_line_2'),
            suburb: $request->input('suburb'),
            state: $request->input('state'),
            postcode: $request->input('postcode'),
            countryCode: $request->input('country_code', 'AU'),
            locationMetadata: $request->input('location_metadata'),
        );

        return response()->json([
            'data' => $property,
        ], 201);
    }

    public function show(
        Request $request,
        string $property,
    ): JsonResponse {
        $model = Property::query()
            ->whereKey($property)
            ->where('user_id', $request->user()->getKey())
            ->with('periods')
            ->firstOrFail();

        return response()->json([
            'data' => $model,
        ]);
    }

    public function storePeriod(
        StorePropertyPeriodRequest $request,
        string $property,
        CreatePropertyPeriod $createPropertyPeriod,
    ): JsonResponse {
        $model = Property::query()
            ->whereKey($property)
            ->where('user_id', $request->user()->getKey())
            ->firstOrFail();

        $period = $createPropertyPeriod->execute(
            user: $request->user(),
            property: $model,
            periodStart: $request->string('period_start')->toString(),
            periodEnd: $request->filled('period_end')
                ? $request->string('period_end')->toString()
                : null,
            useType: $request->string('use_type')->toString(),
            ownershipPercentage: $request->filled('ownership_percentage')
                ? $request->input('ownership_percentage')
                : null,
            provenance: $request->input('provenance'),
        );

        return response()->json([
            'data' => $period,
        ], 201);
    }
}
