<?php

namespace App\Http\Middleware;

use App\Application\Audit\AuditLogger;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('audit:document.download,document'). Records an audit
 * event after a successful response; the target id is the {id} route
 * parameter, or data.id of a JSON response (e.g. creations).
 */
class AuditRequest
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Request $request, Closure $next, string $event, ?string $targetType = null): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() >= 400 || $request->user() === null) {
            return $response;
        }

        $targetId = $request->route('id') ?? $request->route('property');

        if ($targetId === null && $response instanceof JsonResponse) {
            $targetId = $response->getData(true)['data']['id'] ?? null;
        }

        $this->audit->record(
            $request->attributes->get('actor') ?? $request->user(),
            $event,
            $targetType,
            is_string($targetId) ? $targetId : null,
            array_filter([
                'financial_year_id' => $request->input('financial_year_id'),
                'status' => $response->getStatusCode(),
                'acting_for' => $request->attributes->has('actor') ? $request->user()->getKey() : null,
            ]),
        );

        return $response;
    }
}
