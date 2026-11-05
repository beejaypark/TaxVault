<?php

namespace App\Http\Controllers\Api;

use App\Domain\Properties\Models\Mortgage;
use App\Domain\Properties\Models\MortgageInterestEntry;
use App\Domain\Properties\Models\MortgageRateChange;
use App\Domain\Properties\Models\Property;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MortgageController extends Controller
{
    public function store(Request $request, string $property): JsonResponse
    {
        $model = Property::query()
            ->whereKey($property)
            ->where('user_id', $request->user()->getKey())
            ->firstOrFail();

        $mortgage = Mortgage::query()->create([
            ...$this->validated($request),
            'user_id' => $request->user()->getKey(),
            'property_id' => $model->getKey(),
        ]);

        if ($mortgage->interest_rate !== null) {
            $this->recordRate($mortgage, substr((string) ($mortgage->start_date ?? now()->toDateString()), 0, 10), $mortgage->interest_rate);
        }

        return response()->json(['data' => $mortgage->fresh()], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $mortgage = $this->findOwned($request, $id);
        $before = $mortgage->interest_rate;
        $mortgage->update($this->validated($request));

        if ($mortgage->interest_rate !== null && (float) $mortgage->interest_rate !== (float) $before) {
            $this->recordRate($mortgage, now()->toDateString(), $mortgage->interest_rate);
        }

        return response()->json(['data' => $mortgage->refresh()]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $mortgage = $this->findOwned($request, $id);

        DB::transaction(function () use ($mortgage): void {
            $mortgage->interestEntries()->delete();
            $mortgage->delete();
        });

        return response()->json(['message' => 'Mortgage deleted.']);
    }

    public function saveInterest(Request $request, string $id): JsonResponse
    {
        $mortgage = $this->findOwned($request, $id);
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'interest_amount' => ['required', 'numeric', 'min:0', 'max:999999999999'],
        ]);

        MortgageInterestEntry::query()->updateOrCreate(
            ['mortgage_id' => $mortgage->getKey(), 'month' => $data['month'].'-01'],
            ['interest_amount' => $data['interest_amount']],
        );

        return response()->json(['data' => $mortgage->refresh()->refresh()]);
    }

    public function saveRate(Request $request, string $id): JsonResponse
    {
        $mortgage = $this->findOwned($request, $id);
        $data = $request->validate([
            'effective_date' => ['required', 'date'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $this->recordRate($mortgage, $data['effective_date'], $data['rate']);

        return response()->json(['data' => $mortgage->refresh()]);
    }

    public function destroyRate(Request $request, string $id, string $entry): JsonResponse
    {
        $mortgage = $this->findOwned($request, $id);

        $mortgage->rateChanges()->whereKey($entry)->delete();
        $mortgage->syncCurrentRate();

        return response()->json(['data' => $mortgage->refresh()]);
    }

    public function destroyInterest(Request $request, string $id, string $entry): JsonResponse
    {
        $mortgage = $this->findOwned($request, $id);

        $mortgage->interestEntries()->whereKey($entry)->delete();

        return response()->json(['data' => $mortgage->refresh()->refresh()]);
    }

    private function recordRate(Mortgage $mortgage, string $date, $rate): void
    {
        MortgageRateChange::query()->updateOrCreate(
            ['mortgage_id' => $mortgage->getKey(), 'effective_date' => $date],
            ['rate' => $rate],
        );
        $mortgage->syncCurrentRate();
    }

    private function findOwned(Request $request, string $id): Mortgage
    {
        return Mortgage::query()
            ->whereKey($id)
            ->where('user_id', $request->user()->getKey())
            ->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'bank_name' => ['required', 'string', 'max:100'],
            'loan_type' => ['required', 'in:variable,fixed,interest_only'],
            'loan_amount' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'start_date' => ['nullable', 'date'],
        ]);
    }
}
