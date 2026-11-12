<?php

namespace App\Http\Controllers\Api;

use App\Domain\Income\Models\RecurringIncomeRule;
use App\Domain\Transactions\AtoCategory;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class RecurringIncomeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => RecurringIncomeRule::query()->where('user_id', $request->user()->getKey())->orderBy('active', 'desc')->orderBy('name')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:1000'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'day_of_month' => ['required', 'integer', 'between:1,28'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'ato_category' => ['nullable', 'string', Rule::in(AtoCategory::codes())],
        ]);
        $data['user_id'] = $request->user()->getKey();
        $rule = RecurringIncomeRule::create($data);
        return response()->json(['data' => $rule], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $rule = RecurringIncomeRule::query()->where('user_id', $request->user()->getKey())->findOrFail($id);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'], 'description' => ['sometimes', 'string', 'max:1000'],
            'amount' => ['sometimes', 'numeric', 'gt:0'], 'day_of_month' => ['sometimes', 'integer', 'between:1,28'],
            'start_date' => ['sometimes', 'date'], 'end_date' => ['nullable', 'date'],
            'ato_category' => ['nullable', 'string', Rule::in(AtoCategory::codes())], 'active' => ['sometimes', 'boolean'],
        ]);
        $rule->update($data);
        return response()->json(['data' => $rule->refresh()]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        RecurringIncomeRule::query()->where('user_id', $request->user()->getKey())->findOrFail($id)->delete();
        return response()->json(['message' => 'Recurring income rule deleted.']);
    }
}
