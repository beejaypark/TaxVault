<?php

namespace Tests\Feature\Api\Exports;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialYearExportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_financial_year_export(): void
    {
        $user = User::factory()->create([
            'identity_provider' => 'test',
            'provider_subject' => 'export-api-create-user',
            'email' => 'export-api-create@example.com',
            'display_name' => 'Export API Create User',
            'status' => 'active',
        ]);

        $financialYear = $user->financialYears()->create([
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->postJson('/api/exports', [
            'financial_year_id' => $financialYear->id,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.financial_year_id', $financialYear->id)
            ->assertJsonPath('data.export_version', '1')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'financial_year_id',
                    'export_version',
                    'generated_at',
                    'sha256',
                    'payload' => [
                        'manifest',
                        'financial_year',
                        'tax_records',
                        'source_references',
                        'evidence_chain',
                        'assets',
                        'verification_history',
                        'audit',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('financial_year_export_archives', [
            'id' => $response->json('data.id'),
            'user_id' => $user->id,
            'financial_year_id' => $financialYear->id,
            'export_version' => '1',
            'sha256' => $response->json('data.sha256'),
        ]);
    }

    public function test_user_cannot_export_another_users_financial_year(): void
    {
        $owner = User::factory()->create([
            'identity_provider' => 'test',
            'provider_subject' => 'export-api-owner',
            'email' => 'export-api-owner@example.com',
            'display_name' => 'Export API Owner',
            'status' => 'active',
        ]);

        $otherUser = User::factory()->create([
            'identity_provider' => 'test',
            'provider_subject' => 'export-api-other',
            'email' => 'export-api-other@example.com',
            'display_name' => 'Export API Other',
            'status' => 'active',
        ]);

        $financialYear = $owner->financialYears()->create([
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
        ]);

        $this->actingAs($otherUser)
            ->postJson('/api/exports', [
                'financial_year_id' => $financialYear->id,
            ])
            ->assertNotFound();

        $this->assertDatabaseCount(
            'financial_year_export_archives',
            0,
        );
    }

    public function test_user_can_retrieve_their_own_export(): void
    {
        $user = User::factory()->create([
            'identity_provider' => 'test',
            'provider_subject' => 'export-api-retrieve-user',
            'email' => 'export-api-retrieve@example.com',
            'display_name' => 'Export API Retrieve User',
            'status' => 'active',
        ]);

        $financialYear = $user->financialYears()->create([
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
        ]);

        $createResponse = $this->actingAs($user)
            ->postJson('/api/exports', [
                'financial_year_id' => $financialYear->id,
            ])
            ->assertCreated();

        $exportId = $createResponse->json('data.id');

        $this->actingAs($user)
            ->getJson("/api/exports/{$exportId}")
            ->assertOk()
            ->assertJsonPath('data.id', $exportId)
            ->assertJsonPath('data.financial_year_id', $financialYear->id)
            ->assertJsonPath(
                'data.sha256',
                $createResponse->json('data.sha256'),
            );
    }

    public function test_user_cannot_retrieve_another_users_export(): void
    {
        $owner = User::factory()->create([
            'identity_provider' => 'test',
            'provider_subject' => 'export-api-retrieve-owner',
            'email' => 'export-api-retrieve-owner@example.com',
            'display_name' => 'Export API Retrieve Owner',
            'status' => 'active',
        ]);

        $otherUser = User::factory()->create([
            'identity_provider' => 'test',
            'provider_subject' => 'export-api-retrieve-other',
            'email' => 'export-api-retrieve-other@example.com',
            'display_name' => 'Export API Retrieve Other',
            'status' => 'active',
        ]);

        $financialYear = $owner->financialYears()->create([
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
        ]);

        $createResponse = $this->actingAs($owner)
            ->postJson('/api/exports', [
                'financial_year_id' => $financialYear->id,
            ])
            ->assertCreated();

        $exportId = $createResponse->json('data.id');

        $this->actingAs($otherUser)
            ->getJson("/api/exports/{$exportId}")
            ->assertNotFound();
    }

    public function test_unauthenticated_user_cannot_access_exports(): void
    {
        $this->postJson('/api/exports', [])
            ->assertUnauthorized();

        $this->getJson('/api/exports/00000000-0000-0000-0000-000000000000')
            ->assertUnauthorized();
    }

    public function test_financial_year_id_is_required(): void
    {
        $user = User::factory()->create([
            'identity_provider' => 'test',
            'provider_subject' => 'export-api-validation',
            'email' => 'export-api-validation@example.com',
            'display_name' => 'Export API Validation',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->postJson('/api/exports', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'financial_year_id',
            ]);
    }

    public function test_export_payload_contains_required_archive_metadata(): void
    {
        $user = User::factory()->create([
            'identity_provider' => 'test',
            'provider_subject' => 'export-api-metadata',
            'email' => 'export-api-metadata@example.com',
            'display_name' => 'Export API Metadata',
            'status' => 'active',
        ]);

        $financialYear = $user->financialYears()->create([
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/exports', [
                'financial_year_id' => $financialYear->id,
            ])
            ->assertCreated();

        $response
            ->assertJsonPath(
                'data.payload.manifest.export_version',
                '1',
            )
            ->assertJsonPath(
                'data.payload.manifest.financial_year_id',
                $financialYear->id,
            )
            ->assertJsonPath(
                'data.payload.manifest.year_code',
                '2025-26',
            );

        $this->assertNotEmpty($response->json('data.generated_at'));
        $this->assertNotEmpty($response->json('data.sha256'));
        $this->assertSame(64, strlen($response->json('data.sha256')));
    }
}
