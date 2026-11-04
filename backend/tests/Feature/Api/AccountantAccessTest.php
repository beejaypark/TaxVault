<?php

namespace Tests\Feature\Api;

use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Transactions\Models\Transaction;
use App\Mail\AccountantAccessMail;
use App\Models\AccountantGrant;
use App\Models\User;
use App\Services\KeycloakAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountantAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private FinancialYear $yearA;

    private FinancialYear $yearB;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->app->instance(KeycloakAdmin::class, new class extends KeycloakAdmin
        {
            public array $deleted = [];

            public function createUser(string $email, string $name, string $temporaryPassword): string
            {
                return (string) Str::uuid();
            }

            public function resetPassword(string $subject, string $temporaryPassword): void {}

            public function deleteUser(string $subject): void
            {
                $this->deleted[] = $subject;
            }
        });

        $this->owner = User::factory()->create();
        $this->yearA = $this->year('2025-26', '2025-07-01', '2026-06-30');
        $this->yearB = $this->year('2026-27', '2026-07-01', '2027-06-30');
        $this->transaction($this->yearA, 'Year A income', 'income');
        $this->transaction($this->yearB, 'Year B expense', 'expense');
    }

    private function year(string $code, string $start, string $end): FinancialYear
    {
        return FinancialYear::query()->create([
            'user_id' => $this->owner->id,
            'year_code' => $code,
            'start_date' => $start,
            'end_date' => $end,
            'status' => 'active',
        ]);
    }

    private function transaction(FinancialYear $year, string $description, string $direction): void
    {
        Transaction::query()->create([
            'user_id' => $this->owner->id,
            'financial_year_id' => $year->id,
            'transaction_date' => $year->start_date->toDateString(),
            'description' => $description,
            'direction' => $direction,
            'amount' => '10.00',
            'currency' => 'AUD',
        ]);
    }

    private function accountant(array $sections = ['transactions'], ?array $years = null): User
    {
        $accountant = User::factory()->create(['role' => 'accountant']);

        AccountantGrant::query()->create([
            'owner_id' => $this->owner->id,
            'accountant_user_id' => $accountant->id,
            'name' => 'Ann Accountant',
            'email' => $accountant->email,
            'status' => 'active',
            'financial_year_ids' => $years,
            'sections' => $sections,
        ]);

        return $accountant;
    }

    public function test_owner_invites_accountant_and_receives_one_time_password(): void
    {
        $response = $this->actingAs($this->owner)->postJson('/api/accountants', [
            'name' => 'Ann Accountant',
            'email' => 'Ann@Example.com',
            'firm' => 'Ann & Co',
            'sections' => ['transactions', 'reviews'],
            'financial_year_ids' => [$this->yearA->id],
        ])->assertCreated();

        $this->assertNotEmpty($response->json('data.temporary_password'));
        $this->assertDatabaseHas('users', ['email' => 'ann@example.com', 'role' => 'accountant']);
        $this->assertDatabaseHas('accountant_grants', ['owner_id' => $this->owner->id, 'email' => 'ann@example.com']);
        Mail::assertSent(AccountantAccessMail::class, fn ($mail) => $mail->hasTo('ann@example.com') && $mail->kind === 'invitation');

        $list = $this->actingAs($this->owner)->getJson('/api/accountants')->assertOk();
        $this->assertArrayNotHasKey('temporary_password', $list->json('data.0'));
    }

    public function test_cannot_invite_an_existing_account_holder_or_foreign_year(): void
    {
        $other = User::factory()->create();

        $this->actingAs($this->owner)->postJson('/api/accountants', [
            'name' => 'X', 'email' => $other->email, 'sections' => ['transactions'],
        ])->assertStatus(422);

        $foreignYear = FinancialYear::query()->create([
            'user_id' => $other->id, 'year_code' => '2026-27',
            'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'status' => 'active',
        ]);

        $this->actingAs($this->owner)->postJson('/api/accountants', [
            'name' => 'X', 'email' => 'x@example.com', 'sections' => ['transactions'],
            'financial_year_ids' => [$foreignYear->id],
        ])->assertStatus(422);
    }

    public function test_accountant_reads_owner_data_but_cannot_change_anything(): void
    {
        $accountant = $this->accountant();
        $this->actingAs($accountant);

        $transactions = $this->getJson('/api/transactions')->assertOk()->json('data');
        $this->assertCount(2, $transactions);

        $this->postJson('/api/transactions', [
            'transaction_date' => '2026-08-01', 'direction' => 'expense', 'amount' => '5', 'description' => 'x',
        ])->assertForbidden();
        $this->postJson('/api/financial-years', [
            'year_code' => '2027-28', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30',
        ])->assertForbidden();
        $this->deleteJson('/api/documents/'.Str::uuid())->assertForbidden();
        $this->getJson('/api/accountants')->assertForbidden();
        $this->getJson('/api/inbound-email')->assertForbidden();

        $this->assertDatabaseCount('transactions', 2);
    }

    public function test_sections_limit_what_an_accountant_can_open(): void
    {
        $this->actingAs($this->accountant(['transactions']));

        $this->getJson('/api/transactions')->assertOk();
        $this->getJson('/api/documents')->assertForbidden();
        $this->getJson('/api/reviews')->assertForbidden();
        $this->getJson('/api/properties')->assertForbidden();
    }

    public function test_financial_year_scope_hides_other_years(): void
    {
        $this->actingAs($this->accountant(['transactions'], [$this->yearA->id]));

        $years = $this->getJson('/api/financial-years')->assertOk()->json('data');
        $this->assertSame([$this->yearA->id], array_column($years, 'id'));

        $descriptions = array_column($this->getJson('/api/transactions')->json('data'), 'description');
        $this->assertSame(['Year A income'], $descriptions);

        $this->getJson('/api/exports/transactions.csv?financial_year_id='.$this->yearB->id)->assertNotFound();
        $this->getJson('/api/exports/transactions.csv?financial_year_id='.$this->yearA->id)->assertOk();
    }

    public function test_owner_still_sees_everything_after_an_accountant_request(): void
    {
        $this->actingAs($this->accountant(['transactions'], [$this->yearA->id]))->getJson('/api/transactions')->assertOk();

        $this->app->forgetScopedInstances();
        $this->actingAs($this->owner);
        $this->assertCount(2, $this->getJson('/api/transactions')->json('data'));
    }

    public function test_revoking_removes_access_and_deactivates_the_account(): void
    {
        $accountant = $this->accountant();
        $grant = AccountantGrant::query()->where('accountant_user_id', $accountant->id)->firstOrFail();

        $this->actingAs($this->owner)->deleteJson("/api/accountants/{$grant->id}")->assertOk();

        $this->assertDatabaseMissing('accountant_grants', ['id' => $grant->id]);
        $this->assertSame('inactive', $accountant->refresh()->status);

        $this->app->forgetScopedInstances();
        $this->actingAs($accountant)->getJson('/api/transactions')->assertForbidden();
    }

    public function test_accountant_cannot_manage_another_owners_grants(): void
    {
        $accountant = $this->accountant();
        $grant = AccountantGrant::query()->where('accountant_user_id', $accountant->id)->firstOrFail();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->patchJson("/api/accountants/{$grant->id}", [
            'name' => 'Hacked', 'sections' => ['documents'],
        ])->assertNotFound();
        $this->actingAs($stranger)->deleteJson("/api/accountants/{$grant->id}")->assertNotFound();
    }

    public function test_send_pack_emails_a_link_within_the_granted_years(): void
    {
        $accountant = $this->accountant(['transactions'], [$this->yearA->id]);
        $grant = AccountantGrant::query()->where('accountant_user_id', $accountant->id)->firstOrFail();

        $this->actingAs($this->owner)->postJson("/api/accountants/{$grant->id}/send-pack", [
            'financial_year_id' => $this->yearB->id,
        ])->assertStatus(422);

        $this->actingAs($this->owner)->postJson("/api/accountants/{$grant->id}/send-pack", [
            'financial_year_id' => $this->yearA->id, 'note' => 'Please review',
        ])->assertOk();

        Mail::assertSent(AccountantAccessMail::class, fn ($mail) => $mail->kind === 'pack' && $mail->yearLabel === '2025-26');
    }
}
