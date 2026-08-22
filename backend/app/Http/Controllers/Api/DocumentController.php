<?php

namespace App\Http\Controllers\Api;

use App\Application\Documents\CreateDocument;
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
        CreateDocument $createDocument,
    ): JsonResponse {
        $document = $createDocument->execute(
            user: $request->user(),
            financialYearId: $request->string('financial_year_id')->toString(),
            documentTypeId: $request->string('document_type_id')->toString(),
            storageDisk: $request->string('storage_disk')->toString(),
            objectKey: $request->string('object_key')->toString(),
            contentSha256: $request->string('content_sha256')->toString(),
            originalFilename: $request->input('original_filename'),
            mimeType: $request->input('mime_type'),
            sizeBytes: $request->integer('size_bytes'),
            capturedAt: $request->input('captured_at'),
            uploadedAt: $request->input('uploaded_at'),
            provenance: $request->input('provenance'),
            status: $request->input('status', 'active'),
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
