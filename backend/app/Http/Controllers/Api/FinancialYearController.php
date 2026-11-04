<?php

namespace App\Http\Controllers\Api;

use App\Application\FinancialYears\CreateFinancialYear;
use App\Domain\FinancialYears\Models\FinancialYear;
use App\Http\Controllers\Controller;
use App\Http\Requests\FinancialYears\StoreFinancialYearRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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

    public function update(Request $request, string $id): JsonResponse
    {
        $financialYear = $this->findOwned($request, $id);

        $data = $request->validate([
            'year_code' => ['sometimes', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
        ]);

        $codeChanged = isset($data['year_code']) && $data['year_code'] !== $financialYear->year_code;

        if ($codeChanged) {
            if ($this->hasRecords($financialYear)) {
                throw ValidationException::withMessages([
                    'year_code' => 'This financial year already has records, so its period cannot be changed.',
                ]);
            }

            $startYear = (int) substr($data['year_code'], 0, 4);

            if ((int) substr($data['year_code'], 5, 2) !== ($startYear + 1) % 100) {
                throw ValidationException::withMessages([
                    'year_code' => 'The second part must be the following year, e.g. 2025-26.',
                ]);
            }

            $start = sprintf('%04d-07-01', $startYear);
            $end = sprintf('%04d-06-30', $startYear + 1);

            $overlaps = FinancialYear::query()
                ->where('user_id', $financialYear->user_id)
                ->whereKeyNot($financialYear->getKey())
                ->whereDate('start_date', '<=', $end)
                ->whereDate('end_date', '>=', $start)
                ->exists();

            if ($overlaps) {
                throw ValidationException::withMessages([
                    'year_code' => 'That financial year overlaps an existing one.',
                ]);
            }

            $financialYear->fill([
                'year_code' => $data['year_code'],
                'start_date' => $start,
                'end_date' => $end,
            ]);
        }

        if (isset($data['status'])) {
            $financialYear->status = $data['status'];
        }

        $financialYear->save();

        return response()->json(['data' => $financialYear->refresh()]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $financialYear = $this->findOwned($request, $id);

        if ($this->hasRecords($financialYear)) {
            return response()->json([
                'message' => 'This financial year has documents or transactions. Remove them first, or set the year to inactive.',
            ], 409);
        }

        $financialYear->delete();

        return response()->json(['message' => 'Financial year deleted.']);
    }

    private function findOwned(Request $request, string $id): FinancialYear
    {
        return FinancialYear::query()
            ->whereKey($id)
            ->where('user_id', $request->user()->getKey())
            ->firstOrFail();
    }

    private function hasRecords(FinancialYear $financialYear): bool
    {
        foreach (['documents', 'transactions', 'income', 'expenses', 'tax_evidences', 'financial_year_export_archives'] as $table) {
            if (DB::table($table)->where('financial_year_id', $financialYear->getKey())->exists()) {
                return true;
            }
        }

        return false;
    }
}
