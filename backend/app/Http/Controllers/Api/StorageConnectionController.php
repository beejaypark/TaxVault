<?php

namespace App\Http\Controllers\Api;

use App\Application\Documents\StorageProviderRegistry;
use App\Domain\Documents\Models\StorageConnection;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StorageConnectionController extends Controller
{
    public function index(Request $request, StorageProviderRegistry $registry): JsonResponse
    {
        return response()->json([
            'data' => StorageConnection::query()
                ->where('user_id', $request->user()->getKey())
                ->orderBy('provider')
                ->orderBy('display_name')
                ->get(),
            'providers' => $registry->descriptors(),
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $connection = StorageConnection::query()
            ->where('user_id', $request->user()->getKey())
            ->whereKey($id)
            ->firstOrFail();

        // Keep the connection record so imported documents retain provenance.
        $connection->update([
            'status' => 'disconnected',
            'last_error' => null,
        ]);

        return response()->json(['data' => $connection->fresh()]);
    }
}
