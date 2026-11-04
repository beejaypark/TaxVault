<?php

namespace Tests\Feature\Security;

use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentDeletionPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_document_erases_the_stored_file_and_is_audited(): void
    {
        Storage::fake('documents');
        $user = User::factory()->create();
        $type = DocumentType::query()->create([
            'code' => 'other', 'name' => 'Other', 'description' => 'x', 'metadata' => [], 'status' => 'active',
        ]);
        Storage::disk('documents')->put('unassigned/a.pdf', 'content');
        $document = Document::query()->create([
            'user_id' => $user->id,
            'document_type_id' => $type->id,
            'storage_disk' => 'documents',
            'object_key' => 'unassigned/a.pdf',
            'content_sha256' => hash('sha256', 'content'),
            'original_filename' => 'a.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 7,
            'uploaded_at' => now(),
            'status' => 'active',
        ]);

        $this->actingAs($user)->deleteJson("/api/documents/{$document->id}")->assertOk();

        Storage::disk('documents')->assertMissing('unassigned/a.pdf');
        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
        $this->assertDatabaseHas('audit_events', [
            'actor_user_id' => $user->id,
            'event_type' => 'document.delete',
            'target_id' => $document->id,
        ]);
    }

    public function test_orphan_purge_only_removes_old_unreferenced_files(): void
    {
        Storage::fake('documents');
        $disk = Storage::disk('documents');
        $disk->put('x/orphan.pdf', 'o');
        $disk->put('x/fresh.pdf', 'f');
        touch(Storage::disk('documents')->path('x/orphan.pdf'), now()->subDays(3)->getTimestamp());

        $this->artisan('documents:purge-orphans')->expectsOutputToContain('Found 1 orphan');
        $disk->assertExists('x/orphan.pdf');

        $this->artisan('documents:purge-orphans', ['--force' => true])->expectsOutputToContain('Deleted 1 orphan');
        $disk->assertMissing('x/orphan.pdf');
        $disk->assertExists('x/fresh.pdf');
        $this->assertSame(1, DB::table('audit_events')->where('event_type', 'document.orphan_purged')->count());
    }
}
