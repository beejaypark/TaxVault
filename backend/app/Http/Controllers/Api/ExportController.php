<?php

namespace App\Http\Controllers\Api;

use App\Domain\Reporting\Models\FinancialYearExportArchive;
use App\Domain\Reporting\Services\FinancialYearExportService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reporting\CreateFinancialYearExportRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ExportController extends Controller
{
    public function store(
        CreateFinancialYearExportRequest $request,
        FinancialYearExportService $exportService,
    ): JsonResponse {
        $export = $exportService->export(
            user: $request->user(),
            financialYearId: $request->string('financial_year_id')->toString(),
        );

        $archive = FinancialYearExportArchive::query()->create([
            'user_id' => $request->user()->getKey(),
            'financial_year_id' => $request->string('financial_year_id')->toString(),
            'export_version' => $export->payload['manifest']['export_version'],
            'sha256' => $export->sha256,
            'generated_at' => $export->payload['manifest']['generated_at'],
            'payload' => $export->payload,
        ]);

        return response()->json([
            'data' => [
                'id' => $archive->getKey(),
                'financial_year_id' => $archive->financial_year_id,
                'export_version' => $archive->export_version,
                'generated_at' => $archive->generated_at->toIso8601String(),
                'sha256' => $archive->sha256,
                'payload' => $archive->payload,
            ],
        ], 201);
    }

    public function show(
        Request $request,
        string $id,
    ): JsonResponse {
        $archive = FinancialYearExportArchive::query()
            ->whereKey($id)
            ->where('user_id', $request->user()->getKey())
            ->first();

        if ($archive === null) {
            throw (new ModelNotFoundException)->setModel(
                FinancialYearExportArchive::class,
                [$id],
            );
        }

        return response()->json([
            'data' => [
                'id' => $archive->getKey(),
                'financial_year_id' => $archive->financial_year_id,
                'export_version' => $archive->export_version,
                'generated_at' => $archive->generated_at->toIso8601String(),
                'sha256' => $archive->sha256,
                'payload' => $archive->payload,
            ],
        ]);
    }
}

