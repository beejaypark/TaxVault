<?php

namespace App\Http\Controllers\Api;

use App\Application\Investments\CreateInvestment;
use App\Domain\Investments\Models\Investment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Investments\StoreInvestmentRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class InvestmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $investments = Investment::query()
            ->where('user_id', $request->user()->getKey())
            ->orderByDesc('acquisition_date')
            ->get();

        return response()->json([
            'data' => $investments,
        ]);
    }

    public function store(
        StoreInvestmentRequest $request,
        CreateInvestment $createInvestment,
    ): JsonResponse {
        $investment = $createInvestment->execute(
            user: $request->user(),
            investmentType: $request->string('investment_type')->toString(),
            acquisitionDate: $request->string('acquisition_date')->toString(),
            disposalDate: $request->input('disposal_date'),
            propertyId: $request->input('property_id'),
            quantity: $request->input('quantity'),
            ownershipPercentage: $request->input('ownership_percentage'),
            costBase: $request->input('cost_base'),
            incidentalCosts: $request->input('incidental_costs'),
            proceeds: $request->input('proceeds'),
            sourceSystem: $request->input('source_system'),
            externalId: $request->input('external_id'),
            metadata: $request->input('metadata'),
        );

        return response()->json([
            'data' => $investment,
        ], 201);
    }

    public function show(
        Request $request,
        string $id,
    ): JsonResponse {
        $investment = Investment::query()
            ->whereKey($id)
            ->where('user_id', $request->user()->getKey())
            ->first();

        if ($investment === null) {
            throw (new ModelNotFoundException)->setModel(
                Investment::class,
                [$id],
            );
        }

        return response()->json([
            'data' => $investment,
        ]);
    }
}
