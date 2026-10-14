<?php

namespace App\Http\Controllers\Api;

use App\Application\Documents\UploadDocument;
use App\Domain\Documents\Models\Document;
use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\StoreDocumentRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $documents = Document::query()
            ->where('user_id', $request->user()->getKey())
            ->orderByDesc('uploaded_at')
            ->get();

        return response()->json([
            'data' => $documents,
        ]);
    }

    public function store(
        StoreDocumentRequest $request,
        UploadDocument $uploadDocument,
    ): JsonResponse {
        $validated = $request->validated();

        $document = $uploadDocument->execute(
            user: $request->user(),
            financialYearId: $validated['financial_year_id'],
            documentTypeId: $validated['document_type_id'],
            file: $request->file('file'),
        );

        return response()->json([
            'data' => $document,
        ], 201);
    }

    public function show(
        Request $request,
        string $id,
    ): JsonResponse {
        $document = Document::query()
            ->whereKey($id)
            ->where('user_id', $request->user()->getKey())
            ->first();

        if ($document === null) {
            throw (new ModelNotFoundException)->setModel(
                Document::class,
                [$id],
            );
        }

        return response()->json([
            'data' => $document,
        ]);
    }
}