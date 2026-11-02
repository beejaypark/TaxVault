<?php

namespace Tests\Feature\Database;

use App\Domain\Documents\Models\DocumentType;
use Database\Seeders\StarterDocumentTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StarterDocumentTypesSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_active_starter_document_types_without_overwriting_existing_configuration(): void
    {
        $this->seed(StarterDocumentTypesSeeder::class);

        $this->assertDatabaseCount('document_types', 6);
        $this->assertDatabaseHas('document_types', [
            'code' => 'receipt',
            'name' => 'Receipt',
            'status' => 'active',
            'version' => 'starter-1.0',
        ]);

        DocumentType::query()
            ->where('code', 'receipt')
            ->update([
                'name' => 'Custom receipt label',
                'status' => 'inactive',
            ]);

        $this->seed(StarterDocumentTypesSeeder::class);

        $this->assertDatabaseCount('document_types', 6);
        $this->assertDatabaseHas('document_types', [
            'code' => 'receipt',
            'name' => 'Custom receipt label',
            'status' => 'inactive',
        ]);
    }
}
