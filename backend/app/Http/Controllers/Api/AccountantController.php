<?php

namespace App\Http\Controllers\Api;

use App\Domain\FinancialYears\Models\FinancialYear;
use App\Http\Controllers\Controller;
use App\Mail\AccountantAccessMail;
use App\Models\AccountantGrant;
use App\Models\User;
use App\Services\KeycloakAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Owner-only management of the read-only accountants invited to this account. */
final class AccountantController extends Controller
{
    public function __construct(private readonly KeycloakAdmin $keycloak) {}

    public function index(Request $request): JsonResponse
    {
        $grants = AccountantGrant::query()
            ->where('owner_id', $request->user()->getKey())
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $grants->map(fn ($grant) => $this->present($grant))]);
    }

    public function store(Request $request): JsonResponse
    {
        $owner = $request->user();
        $data = $this->validated($request, withIdentity: true);

        $existing = User::query()->where('email', $data['email'])->first();

        if ($existing !== null && ! $existing->isAccountant()) {
            throw ValidationException::withMessages([
                'email' => 'This email already belongs to a TaxVault account holder.',
            ]);
        }

        if ($existing !== null && AccountantGrant::query()
            ->where('owner_id', $owner->getKey())
            ->where('accountant_user_id', $existing->getKey())
            ->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This accountant has already been added.',
            ]);
        }

        $temporaryPassword = null;
        $subject = null;
        $needsAccount = $existing === null || $existing->status !== 'active';

        try {
            if ($needsAccount) {
                $temporaryPassword = $this->temporaryPassword();
                $subject = $this->keycloak->createUser($data['email'], $data['name'], $temporaryPassword);
            }
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json(['message' => $exception->getMessage()], 502);
        }

        try {
            $grant = DB::transaction(function () use ($owner, $data, $existing, $subject): AccountantGrant {
                $accountant = $existing ?? new User;

                if ($subject !== null) {
                    $accountant->fill([
                        'identity_provider' => 'keycloak',
                        'provider_subject' => $subject,
                        'status' => 'active',
                        'deactivated_at' => null,
                    ]);
                }

                $accountant->fill([
                    'email' => $data['email'],
                    'display_name' => $data['name'],
                    'role' => 'accountant',
                ])->save();

                return AccountantGrant::query()->create([
                    'owner_id' => $owner->getKey(),
                    'accountant_user_id' => $accountant->getKey(),
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'firm' => $data['firm'] ?? null,
                    'status' => 'active',
                    'financial_year_ids' => $data['financial_year_ids'] ?? null,
                    'sections' => $data['sections'],
                    'last_invited_at' => now(),
                ]);
            });
        } catch (\Throwable $exception) {
            if ($subject !== null) {
                $this->cleanupAccount($subject);
            }

            throw $exception;
        }

        $this->sendMail($grant, $owner, 'invitation', $this->appUrl('/login'));

        return response()->json([
            'data' => $this->present($grant) + ['temporary_password' => $temporaryPassword],
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $grant = $this->findGrant($request, $id);
        $data = $this->validated($request, withIdentity: false);

        $grant->update([
            'name' => $data['name'],
            'firm' => $data['firm'] ?? null,
            'financial_year_ids' => $data['financial_year_ids'] ?? null,
            'sections' => $data['sections'],
        ]);

        return response()->json(['data' => $this->present($grant->refresh())]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $grant = $this->findGrant($request, $id);
        $accountant = $grant->accountant;
        $grant->delete();

        $stillHasAccess = AccountantGrant::query()
            ->where('accountant_user_id', $accountant->getKey())
            ->exists();

        if (! $stillHasAccess) {
            $accountant->update(['status' => 'inactive', 'deactivated_at' => now()]);

            if ($accountant->provider_subject !== null) {
                $this->cleanupAccount($accountant->provider_subject);
            }
        }

        return response()->json(['message' => 'Access revoked.']);
    }

    public function resetPassword(Request $request, string $id): JsonResponse
    {
        $grant = $this->findGrant($request, $id);
        $password = $this->temporaryPassword();

        try {
            $this->keycloak->resetPassword($grant->accountant->provider_subject, $password);
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json(['message' => $exception->getMessage()], 502);
        }

        return response()->json(['data' => ['temporary_password' => $password]]);
    }

    public function sendPack(Request $request, string $id): JsonResponse
    {
        $owner = $request->user();
        $grant = $this->findGrant($request, $id);
        $data = $request->validate([
            'financial_year_id' => ['nullable', 'uuid'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $yearLabel = null;

        if (! empty($data['financial_year_id'])) {
            $year = FinancialYear::query()
                ->whereKey($data['financial_year_id'])
                ->where('user_id', $owner->getKey())
                ->first();

            if ($year === null
                || ($grant->financial_year_ids !== null && ! in_array($year->getKey(), $grant->financial_year_ids, true))) {
                throw ValidationException::withMessages([
                    'financial_year_id' => 'This accountant does not have access to that financial year.',
                ]);
            }

            $yearLabel = $year->year_code;
        }

        $this->sendMail($grant, $owner, 'pack', $this->appUrl('/tax-return'), $yearLabel, $data['note'] ?? null);
        $grant->update(['last_pack_sent_at' => now()]);

        return response()->json(['data' => $this->present($grant->refresh())]);
    }

    private function sendMail(
        AccountantGrant $grant,
        User $owner,
        string $kind,
        string $link,
        ?string $yearLabel = null,
        ?string $note = null,
    ): void {
        Mail::to($grant->email)->send(new AccountantAccessMail(
            ownerName: $owner->display_name ?: ($owner->email ?? 'A TaxVault user'),
            accountantName: $grant->name,
            link: $link,
            kind: $kind,
            yearLabel: $yearLabel,
            note: $note,
        ));
    }

    private function findGrant(Request $request, string $id): AccountantGrant
    {
        return AccountantGrant::query()
            ->with('accountant')
            ->whereKey($id)
            ->where('owner_id', $request->user()->getKey())
            ->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $withIdentity): array
    {
        $ownerId = $request->user()->getKey();

        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'firm' => ['nullable', 'string', 'max:160'],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*' => ['string', Rule::in(AccountantGrant::SECTIONS)],
            'financial_year_ids' => ['nullable', 'array', 'min:1'],
            'financial_year_ids.*' => [
                'uuid',
                Rule::exists('financial_years', 'id')->where('user_id', $ownerId),
            ],
        ];

        if ($withIdentity) {
            $rules['email'] = ['required', 'email:rfc', 'max:190'];
        }

        $data = $request->validate($rules);

        if (isset($data['email'])) {
            $data['email'] = Str::lower($data['email']);
        }

        $data['sections'] = array_values(array_unique($data['sections']));

        return $data;
    }

    private function appUrl(string $path): string
    {
        return rtrim(config('app.url'), '/').$path;
    }

    private function temporaryPassword(): string
    {
        return Str::password(16, symbols: false);
    }

    private function cleanupAccount(string $subject): void
    {
        try {
            $this->keycloak->deleteUser($subject);
        } catch (RuntimeException $exception) {
            Log::warning('Could not remove Keycloak account for accountant.', ['subject' => $subject]);
        }
    }

    /** @return array<string, mixed> */
    private function present(AccountantGrant $grant): array
    {
        return [
            'id' => $grant->getKey(),
            'name' => $grant->name,
            'email' => $grant->email,
            'firm' => $grant->firm,
            'status' => $grant->status,
            'financial_year_ids' => $grant->financial_year_ids,
            'sections' => $grant->sections,
            'last_invited_at' => $grant->last_invited_at?->toIso8601String(),
            'last_pack_sent_at' => $grant->last_pack_sent_at?->toIso8601String(),
        ];
    }
}
