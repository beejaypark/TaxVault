<?php

namespace Tests\Feature\Security;

use App\Application\Documents\UploadDocument;
use App\Domain\Documents\Models\DocumentType;
use App\Infrastructure\Storage\FileCipher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class DocumentEncryptionTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/tv-enc-'.bin2hex(random_bytes(4));
        mkdir($this->root);
        config(['filesystems.disks.documents.root' => $this->root]);
        Storage::purge('documents');
        Queue::fake();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root.'/*/*') ?: [] as $file) {
            @unlink($file);
        }
        foreach (glob($this->root.'/*') ?: [] as $dir) {
            @rmdir($dir);
        }
        @rmdir($this->root);

        parent::tearDown();
    }

    private function rawFiles(): array
    {
        return glob($this->root.'/*/*') ?: [];
    }

    public function test_files_are_encrypted_on_disk_and_decrypted_on_read(): void
    {
        $content = "%PDF-1.4\nsecret tax data";
        $user = User::factory()->create();
        $type = DocumentType::query()->create([
            'code' => 'other', 'name' => 'Other', 'description' => 'x', 'metadata' => [], 'status' => 'active',
        ]);

        $document = app(UploadDocument::class)->execute(
            $user,
            null,
            $type->id,
            UploadedFile::fake()->createWithContent('a.pdf', $content),
        );

        $raw = file_get_contents($this->rawFiles()[0]);
        $this->assertTrue(FileCipher::isEncrypted($raw));
        $this->assertStringNotContainsString('secret tax data', $raw);

        $this->assertSame($content, Storage::disk('documents')->get($document->object_key));
        $this->assertSame(hash('sha256', $content), $document->content_sha256);

        $this->actingAs($user)
            ->get("/api/documents/{$document->id}/content")
            ->assertOk()
            ->assertStreamedContent($content);
    }

    public function test_same_plaintext_never_produces_the_same_ciphertext(): void
    {
        Storage::disk('documents')->put('x/a.pdf', 'same');
        Storage::disk('documents')->put('x/b.pdf', 'same');

        $this->assertNotSame(
            file_get_contents($this->root.'/x/a.pdf'),
            file_get_contents($this->root.'/x/b.pdf'),
        );
    }

    public function test_tampered_or_wrongly_keyed_files_are_rejected(): void
    {
        Storage::disk('documents')->put('x/a.pdf', 'data');
        $path = $this->root.'/x/a.pdf';
        $bytes = file_get_contents($path);
        file_put_contents($path, substr($bytes, 0, -1).chr(ord(substr($bytes, -1)) ^ 1));

        $this->expectException(RuntimeException::class);
        Storage::disk('documents')->get('x/a.pdf');
    }

    public function test_legacy_plaintext_is_readable_and_migration_command_encrypts_it(): void
    {
        mkdir($this->root.'/old');
        file_put_contents($this->root.'/old/legacy.pdf', 'legacy plaintext');

        $this->assertSame('legacy plaintext', Storage::disk('documents')->get('old/legacy.pdf'));

        $this->artisan('documents:encrypt-existing', ['--dry-run' => true])
            ->expectsOutputToContain('Found 1 plaintext');
        $this->assertSame('legacy plaintext', file_get_contents($this->root.'/old/legacy.pdf'));

        $this->artisan('documents:encrypt-existing')->expectsOutputToContain('Encrypted 1 plaintext');

        $this->assertTrue(FileCipher::isEncrypted(file_get_contents($this->root.'/old/legacy.pdf')));
        $this->assertSame('legacy plaintext', Storage::disk('documents')->get('old/legacy.pdf'));

        $this->artisan('documents:encrypt-existing')->expectsOutputToContain('Encrypted 0 plaintext');
    }

    public function test_explicit_key_is_validated(): void
    {
        config(['filesystems.document_encryption_key' => 'base64:'.base64_encode('short')]);

        $this->expectException(RuntimeException::class);
        FileCipher::fromConfig();
    }
}
