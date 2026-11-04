<?php

namespace App\Application\Audit;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Writes append-only rows to audit_events. Never put document contents,
 * tokens or passwords in metadata.
 */
final class AuditLogger
{
    public function record(
        ?User $actor,
        string $eventType,
        ?string $targetType = null,
        ?string $targetId = null,
        array $metadata = [],
    ): void {
        $request = app()->bound('request') ? request() : null;

        DB::table('audit_events')->insert([
            'id' => (string) Str::uuid(),
            'actor_user_id' => $actor?->getKey(),
            'event_type' => $eventType,
            'target_type' => $targetType,
            'target_id' => $targetId !== null && Str::isUuid($targetId) ? $targetId : null,
            'occurred_at' => now(),
            'request_id' => $request?->header('X-Request-Id'),
            'source_ip' => $request?->ip(),
            'metadata' => $metadata === [] ? null : json_encode($metadata),
        ]);
    }
}
