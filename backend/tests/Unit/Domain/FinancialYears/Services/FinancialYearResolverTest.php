<?php

namespace Tests\Unit\Domain\FinancialYears\Services;

use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\FinancialYears\Services\FinancialYearResolver;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialYearResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_date_to_the_correct_financial_year(): void
    {
        $user = User::factory()->create();

        $fy = FinancialYear::create([
            'user_id' => $user->id,
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
        ]);

        $resolved = app(FinancialYearResolver::class)
            ->resolve($user->id, '2026-03-15');

        $this->assertTrue($resolved->is($fy));
    }

    public function test_first_day_of_financial_year_is_included(): void
    {
        $user = User::factory()->create();

        $fy = FinancialYear::create([
            'user_id' => $user->id,
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
        ]);

        $resolved = app(FinancialYearResolver::class)
            ->resolve($user->id, '2025-07-01');

        $this->assertTrue($resolved->is($fy));
    }

    public function test_last_day_of_financial_year_is_included(): void
    {
        $user = User::factory()->create();

        $fy = FinancialYear::create([
            'user_id' => $user->id,
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
        ]);

        $resolved = app(FinancialYearResolver::class)
            ->resolve($user->id, '2026-06-30');

        $this->assertTrue($resolved->is($fy));
    }

    public function test_first_day_of_next_financial_year_is_not_resolved_to_previous_year(): void
    {
        $user = User::factory()->create();

        FinancialYear::create([
            'user_id' => $user->id,
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
        ]);

        $this->expectException(ModelNotFoundException::class);

        app(FinancialYearResolver::class)
            ->resolve($user->id, '2026-07-01');
    }

    public function test_it_does_not_resolve_another_users_financial_year(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        FinancialYear::create([
            'user_id' => $owner->id,
            'year_code' => '2025-26',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
        ]);

        $this->expectException(ModelNotFoundException::class);

        app(FinancialYearResolver::class)
            ->resolve($otherUser->id, '2026-03-15');
    }
}
