<?php

namespace Tests\Feature\Security;

use App\Domain\Documents\Models\DocumentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function events(User $user): array
    {
        return DB::table('audit_events')
            ->where('actor_user_id', $user->id)
            ->orderBy('occurred_at')
            ->pluck('event_type')
            ->all();
    }

    public function test_upload_download_and_delete_are_audited(): void
    {
        Storage::fake('documents');
        Queue::fake();
        $user = User::factory()->create();
        $type = DocumentType::query()->create([
            'code' => 'other', 'name' => 'Other', 'description' => 'x', 'metadata' => [], 'status' => 'active',
        ]);

        $id = $this->actingAs($user)->postJson('/api/documents', [
            'document_type_id' => $type->id,
            'file' => UploadedFile::fake()->createWithContent('a.pdf', "%PDF-1.4\naudit"),
        ])->assertCreated()->json('data.id');

        $this->get("/api/documents/{$id}/content")->assertOk();
        DB::table('documents')->where('id', $id)->update(['status' => 'active']);
        $this->deleteJson("/api/documents/{$id}")->assertOk();

        $this->assertSame(
            ['document.upload', 'document.download', 'document.delete'],
            $this->events($user),
        );

        $row = DB::table('audit_events')->where('event_type', 'document.download')->first();
        $this->assertSame($id, $row->target_id);
        $this->assertNotNull($row->source_ip);
    }

    public function test_failed_requests_and_other_tenants_attempts_are_not_logged_as_success(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $missing = '00000000-0000-4000-8000-000000000000';

        $this->actingAs($intruder)->get("/api/documents/{$missing}/content")->assertNotFound();

        $this->assertSame([], $this->events($intruder));
        $this->assertSame([], $this->events($owner));
    }

    public function test_audit_events_are_append_only(): void
    {
        $user = User::factory()->create();
        app(\App\Application\Audit\AuditLogger::class)->record($user, 'test.event');

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('audit_events')->where('event_type', 'test.event')->delete();
    }
}
