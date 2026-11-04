<?php

namespace Tests\Feature\Api\Documents;

use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentType;
use App\Jobs\ProcessUploadedDocument;
use App\Models\InboundEmailAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InboundEmailApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.inbound_email.secret' => 'hook-secret',
            'services.inbound_email.domain' => 'inbox.test',
        ]);
        Storage::fake('documents');
        Queue::fake();
        DocumentType::query()->create([
            'code' => 'other',
            'name' => 'Other',
            'status' => 'active',
            'sort_order' => 1,
        ]);
    }

    public function test_user_gets_a_stable_forwarding_address(): void
    {
        $user = User::factory()->create();

        $first = $this->actingAs($user)->getJson('/api/inbound-email')->assertOk()->json('data.address');
        $second = $this->actingAs($user)->getJson('/api/inbound-email')->json('data.address');

        $this->assertSame($first, $second);
        $this->assertStringEndsWith('@inbox.test', $first);
    }

    public function test_webhook_stores_supported_attachments_for_the_address_owner(): void
    {
        $user = User::factory()->create();
        $address = InboundEmailAddress::forUser($user)->address();

        $payload = [
            'From' => 'shop@example.com',
            'Subject' => 'Your receipt',
            'ToFull' => [['Email' => $address]],
            'Attachments' => [
                ['Name' => 'receipt.pdf', 'Content' => base64_encode('%PDF-1.4 receipt'), 'ContentType' => 'application/pdf'],
                ['Name' => 'malware.exe', 'Content' => base64_encode('MZ'), 'ContentType' => 'application/octet-stream'],
            ],
        ];

        $this->postJson('/api/inbound-email/webhook', $payload, ['X-Inbound-Secret' => 'hook-secret'])
            ->assertOk()
            ->assertJsonPath('data.stored', 1)
            ->assertJsonPath('data.skipped', 1);

        $document = Document::query()->where('user_id', $user->id)->sole();
        $this->assertSame('receipt.pdf', $document->original_filename);
        $this->assertSame('email_forward', $document->provenance['source']);
        Queue::assertPushed(ProcessUploadedDocument::class);

        $this->postJson('/api/inbound-email/webhook', $payload, ['X-Inbound-Secret' => 'hook-secret'])
            ->assertJsonPath('data.stored', 0);
        $this->assertSame(1, Document::query()->count());
    }

    public function test_webhook_rejects_wrong_secret_and_ignores_unknown_recipient(): void
    {
        $this->postJson('/api/inbound-email/webhook', [], ['X-Inbound-Secret' => 'nope'])->assertUnauthorized();
        $this->postJson('/api/inbound-email/webhook', [])->assertUnauthorized();

        $this->postJson('/api/inbound-email/webhook?secret=hook-secret', [
            'OriginalRecipient' => 'unknown@inbox.test',
            'Attachments' => [['Name' => 'a.pdf', 'Content' => base64_encode('%PDF')]],
        ])->assertOk()->assertJsonPath('data.matched', false);

        $this->assertSame(0, Document::query()->count());
    }
}
