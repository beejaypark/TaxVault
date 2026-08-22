<?php

namespace Tests\Unit\Domain\Reporting\Services;

use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Reporting\Services\FinancialYearExportService;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinancialYearExportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exports_the_selected_financial_year(): void
    {
        $user = User::factory()->create();

        $fy = FinancialYear::create([
            'user_id' => $user->id,
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
        ]);

        $result = app(FinancialYearExportService::class)
            ->export($user, $fy->id);

        $this->assertSame(
            $fy->id,
            $result->payload['manifest']['financial_year_id'],
        );

        $this->assertSame(
            '2025-26',
            $result->payload['manifest']['year_code'],
        );

        $this->assertArrayHasKey(
            'tax_records',
            $result->payload,
        );

        $this->assertArrayHasKey(
            'source_references',
            $result->payload,
        );

        $this->assertArrayHasKey(
            'evidence_chain',
            $result->payload,
        );

        $this->assertArrayHasKey(
            'verification_history',
            $result->payload,
        );

        $this->assertArrayHasKey(
            'audit',
            $result->payload,
        );

        $this->assertArrayHasKey(
            'sha256',
            $result->payload['manifest'],
        );

        $this->assertSame(
            64,
            strlen($result->sha256),
        );
    }

    public function test_it_does_not_export_another_users_financial_year(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $fy = FinancialYear::create([
            'user_id' => $owner->id,
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
        ]);

        $this->expectException(ModelNotFoundException::class);

        app(FinancialYearExportService::class)
            ->export($otherUser, $fy->id);
    }

    public function test_it_contains_required_archive_metadata(): void
    {
        $user = User::factory()->create();

        $fy = FinancialYear::create([
            'user_id' => $user->id,
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
        ]);

        $result = app(FinancialYearExportService::class)
            ->export($user, $fy->id);

        $manifest = $result->payload['manifest'];

        $this->assertSame('1', $manifest['export_version']);
        $this->assertNotEmpty($manifest['generated_at']);
        $this->assertSame($fy->id, $manifest['financial_year_id']);
        $this->assertSame('2025-26', $manifest['year_code']);

        $this->assertIsArray($manifest['record_counts']);
        $this->assertSame($result->sha256, $manifest['sha256']);
    }

    public function test_export_json_is_valid_json(): void
    {
        $user = User::factory()->create();

        $fy = FinancialYear::create([
            'user_id' => $user->id,
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
        ]);

        $result = app(FinancialYearExportService::class)
            ->export($user, $fy->id);

        $decoded = json_decode(
            $result->json,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->assertIsArray($decoded);
        $this->assertSame(
            $result->sha256,
            $decoded['manifest']['sha256'],
        );
    }

    public function test_export_is_scoped_to_the_selected_financial_year(): void
    {
        $user = User::factory()->create();

        $fy2025 = FinancialYear::create([
            'user_id' => $user->id,
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
        ]);

        $fy2026 = FinancialYear::create([
            'user_id' => $user->id,
            'year_code' => '2026-27',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'status' => 'active',
        ]);

        DB::table('transactions')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'financial_year_id' => $fy2025->id,
            'transaction_date' => '2026-03-01',
            'direction' => 'expense',
            'amount' => 100,
            'currency' => 'AUD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('transactions')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'financial_year_id' => $fy2026->id,
            'transaction_date' => '2026-08-01',
            'direction' => 'expense',
            'amount' => 200,
            'currency' => 'AUD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = app(FinancialYearExportService::class)
            ->export($user, $fy2025->id);

        $transactions =
            $result->payload['tax_records']['transactions'];

        $this->assertCount(1, $transactions);
        $this->assertSame(
            $fy2025->id,
            $transactions[0]['financial_year_id'],
        );
    }
}
