<?php

namespace Tests\Feature\Api\Documents;

use App\Domain\Documents\Models\DocumentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentTypeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_active_document_types(): void
    {
        $user = User::factory()->create();

        $activeType = DocumentType::query()->create([
            'code' => 'UPLOAD_TEST_ACTIVE',
            'name' => 'Test receipt',
            'status' => 'active',
            'sort_order' => 10,
            'version' => 'test',
        ]);

        DocumentType::query()->create([
            'code' => 'UPLOAD_TEST_INACTIVE',
            'name' => 'Inactive receipt',
            'status' => 'inactive',
            'sort_order' => 20,
            'version' => 'test',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/document-types');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $activeType->id)
            ->assertJsonPath('data.0.code', 'UPLOAD_TEST_ACTIVE')
            ->assertJsonPath('data.0.name', 'Test receipt');
    }

    public function test_unauthenticated_user_cannot_list_document_types(): void
    {
        $this->getJson('/api/document-types')
            ->assertUnauthorized();
    }
}