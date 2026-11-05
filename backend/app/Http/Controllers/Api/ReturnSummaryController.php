<?php

namespace App\Http\Controllers\Api;

use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Reporting\Services\ReturnSummaryBuilder;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReturnSummaryController extends Controller
{
    public function show(Request $request, string $id, ReturnSummaryBuilder $builder): JsonResponse
    {
        $userId = $request->user()->getKey();

        $year = FinancialYear::query()
            ->whereKey($id)
            ->where('user_id', $userId)
            ->firstOrFail();

        return response()->json(['data' => $builder->build($userId, $year)]);
    }
}
