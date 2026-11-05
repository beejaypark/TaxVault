<?php

namespace App\Http\Controllers\Api;

use App\Domain\Deductions\Models\WorkFromHomeLog;
use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Reporting\Services\ReturnSummaryBuilder;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WorkFromHomeController extends Controller
{
    public function __construct(private readonly ReturnSummaryBuilder $summary) {}

    public function index(Request $request): JsonResponse
    {
        $year = $this->year($request, (string) $request->query('financial_year_id'));

        $logs = WorkFromHomeLog::query()
            ->where('user_id', $request->user()->getKey())
            ->whereBetween('work_date', [$year->start_date->toDateString(), $year->end_date->toDateString()])
            ->orderBy('work_date')
            ->get(['id', 'work_date', 'hours', 'note']);

        return response()->json(['data' => [
            'summary' => $this->summary->workFromHome($request->user()->getKey(), $year),
            'logs' => $logs,
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'work_date' => ['required', 'date'],
            'hours' => ['required', 'numeric', 'gt:0', 'lte:24'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $userId = $request->user()->getKey();
        $covered = FinancialYear::query()
            ->where('user_id', $userId)
            ->whereDate('start_date', '<=', $data['work_date'])
            ->whereDate('end_date', '>=', $data['work_date'])
            ->exists();

        if (! $covered) {
            throw ValidationException::withMessages([
                'work_date' => 'No financial year covers this date. Add the financial year first.',
            ]);
        }

        $log = WorkFromHomeLog::query()->updateOrCreate(
            ['user_id' => $userId, 'work_date' => $data['work_date']],
            ['hours' => $data['hours'], 'note' => $data['note'] ?? null],
        );

        return response()->json(['data' => $log->only(['id', 'work_date', 'hours', 'note'])], 201);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        WorkFromHomeLog::query()
            ->whereKey($id)
            ->where('user_id', $request->user()->getKey())
            ->firstOrFail()
            ->delete();

        return response()->json(['message' => 'Entry deleted.']);
    }

    public function updateRate(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['rate' => ['required', 'numeric', 'gt:0', 'lte:10']]);
        $year = $this->year($request, $id);

        $year->wfh_rate = $data['rate'];
        $year->save();

        return response()->json([
            'data' => $this->summary->workFromHome($request->user()->getKey(), $year),
        ]);
    }

    private function year(Request $request, string $id): FinancialYear
    {
        validator(['id' => $id], ['id' => ['required', 'uuid']])->validate();

        return FinancialYear::query()
            ->whereKey($id)
            ->where('user_id', $request->user()->getKey())
            ->firstOrFail();
    }
}
