<?php

namespace App\Http\Controllers\Api;

use App\Domain\Documents\Models\DocumentType;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class DocumentTypeController extends Controller
{
    public function index(): JsonResponse
    {
        $documentTypes = DocumentType::query()
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get([
                'id',
                'code',
                'name',
                'description',
                'classification_metadata',
                'version',
            ]);

        return response()->json([
            'data' => $documentTypes,
        ]);
    }
}
