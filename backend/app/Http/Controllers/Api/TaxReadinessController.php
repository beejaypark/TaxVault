<?php

namespace App\Http\Controllers\Api;

use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Reporting\Services\TaxReadinessService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TaxReadinessController extends Controller
{
    public function show(Request $request, string $id, TaxReadinessService $service): JsonResponse
    {
        $year = FinancialYear::query()
            ->whereKey($id)
            ->where('user_id', $request->user()->getKey())
            ->firstOrFail();

        return response()->json([
            'data' => $service->calculate($request->user(), $year)->toArray(),
        ]);
    }
}
