<?php

namespace App\Http\Controllers\Api;

use App\Application\Documents\IngestInboundEmail;
use App\Http\Controllers\Controller;
use App\Models\InboundEmailAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class InboundEmailController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => ['address' => InboundEmailAddress::forUser($request->user())->address()],
        ]);
    }

    public function webhook(Request $request, IngestInboundEmail $ingest): JsonResponse
    {
        $secret = (string) config('services.inbound_email.secret');
        $given = (string) ($request->header('X-Inbound-Secret') ?? $request->query('secret', ''));

        if ($secret === '' || ! hash_equals($secret, $given)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        // Always 200 for unknown recipients so the provider does not retry.
        return response()->json(['data' => $ingest->execute($request->json()->all())]);
    }
}
