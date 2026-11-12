<?php

namespace App\Http\Controllers\Api;

use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Properties\Models\Property;
use App\Domain\Properties\Models\PropertyDeduction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PropertyDeductionController extends Controller
{
    public function store(Request $request, string $property): JsonResponse
    {
        $model = $this->property($request, $property);
        $data = $this->validated($request, $model);
        $deduction = PropertyDeduction::query()->updateOrCreate(
            [
                'property_id' => $model->getKey(),
                'financial_year_id' => $data['financial_year_id'],
                'deduction_type' => $data['deduction_type'],
            ],
            [...$data, 'user_id' => $request->user()->getKey()],
        );

        return response()->json(['data' => $deduction->load('financialYear')], 201);
    }

    public function update(Request $request, string $property, string $deduction): JsonResponse
    {
        $model = $this->property($request, $property);
        $record = PropertyDeduction::query()->where('property_id', $model->getKey())->findOrFail($deduction);
        $record->update($this->validated($request, $model));

        return response()->json(['data' => $record->refresh()->load('financialYear')]);
    }

    public function destroy(Request $request, string $property, string $deduction): JsonResponse
    {
        $model = $this->property($request, $property);
        $record = PropertyDeduction::query()->where('property_id', $model->getKey())->findOrFail($deduction);
        $record->delete();

        return response()->json(['message' => 'Property deduction deleted.']);
    }

    private function property(Request $request, string $property): Property
    {
        return Property::query()->whereKey($property)->where('user_id', $request->user()->getKey())->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, Property $property): array
    {
        $data = $request->validate([
            'financial_year_id' => ['required', 'uuid', Rule::exists('financial_years', 'id')->where('user_id', $request->user()->getKey())],
            'deduction_type' => ['required', Rule::in(['capital_works', 'plant_equipment'])],
            'amount' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'source_document_id' => ['nullable', 'uuid', Rule::exists('documents', 'id')->where('user_id', $request->user()->getKey())],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return $data;
    }
}
