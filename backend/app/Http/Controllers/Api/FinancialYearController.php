<?php

namespace App\Http\Controllers\Api;

use App\Application\FinancialYears\CreateFinancialYear;
use App\Domain\FinancialYears\Models\FinancialYear;
use App\Http\Controllers\Controller;
use App\Http\Requests\FinancialYears\StoreFinancialYearRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialYearController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $financialYears = FinancialYear::query()
            ->where('user_id', $request->user()->getKey())
            ->orderByDesc('start_date')
            ->get();

        return response()->json([
            'data' => $financialYears,
        ]);
    }

    public function store(
        StoreFinancialYearRequest $request,
        CreateFinancialYear $createFinancialYear,
    ): JsonResponse {
        $financialYear = $createFinancialYear->execute(
            user: $request->user(),
            yearCode: $request->string('year_code')->toString(),
            startDate: $request->string('start_date')->toString(),
            endDate: $request->string('end_date')->toString(),
            status: $request->input('status', 'active'),
        );

        return response()->json([
            'data' => $financialYear,
        ], 201);
    }
}
