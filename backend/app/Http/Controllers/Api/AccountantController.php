<?php

namespace App\Http\Controllers\Api;

use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Reporting\Services\AccountantPackAttachmentService;
use App\Domain\Reporting\Services\FinancialYearExportService;
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

        $invitationSent = $this->trySendMail($grant, $owner, 'invitation', $this->appUrl('/login'));

        return response()->json([
            'data' => $this->present($grant) + [
                'temporary_password' => $temporaryPassword,
                'invitation_sent' => $invitationSent,
            ],
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
            'to' => ['nullable', 'email:rfc', 'max:190'],
            'from_email' => ['nullable', 'email:rfc', 'max:190'],
            'from_name' => ['nullable', 'string', 'max:120'],
            'subject' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string', 'max:10000'],
        ]);

        $yearLabel = null;
        $year = null;

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

        if ($year === null) {
            $yearQuery = FinancialYear::query()->where('user_id', $owner->getKey());
            if ($grant->financial_year_ids !== null) {
                $yearQuery->whereIn('id', $grant->financial_year_ids);
            }
            $year = $yearQuery->orderByDesc('start_date')->first();
            $yearLabel = $year?->year_code;
        }

        $attachmentData = null;
        $attachmentName = null;
        $attachments = [];
        if ($year !== null) {
            $export = app(FinancialYearExportService::class)->export($owner, $year->getKey());
            $xlsx = app(AccountantPackAttachmentService::class)->xlsx($export->payload);
            $pdf = app(AccountantPackAttachmentService::class)->pdf($export->payload);
            $attachmentData = $xlsx;
            $attachmentName = sprintf('taxvault-%s-accountant-pack.xlsx', $year->year_code);
            $attachments = [
                ['data' => $xlsx, 'name' => $attachmentName, 'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                ['data' => $pdf, 'name' => sprintf('taxvault-%s-accountant-pack.pdf', $year->year_code), 'mime' => 'application/pdf'],
            ];
        }

        $mailSent = $this->trySendMail(
            grant: $grant,
            owner: $owner,
            kind: 'pack',
            link: $this->appUrl('/tax-return'),
            yearLabel: $yearLabel,
            note: $data['note'] ?? null,
            attachmentData: $attachmentData,
            attachmentName: $attachmentName,
            attachments: $attachments,
            toEmail: $data['to'] ?? $grant->email,
            fromEmail: $data['from_email'] ?? config('mail.from.address'),
            fromName: $data['from_name'] ?? config('mail.from.name'),
            subject: $data['subject'] ?? null,
            content: $data['content'] ?? null,
        );

        if (! $mailSent) {
            return response()->json([
                'message' => 'The accountant pack could not be emailed. The accountant access is unchanged; check the mail server settings and try again.',
            ], 503);
        }

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
        ?string $attachmentData = null,
        ?string $attachmentName = null,
        array $attachments = [],
        ?string $toEmail = null,
        ?string $fromEmail = null,
        ?string $fromName = null,
        ?string $subject = null,
        ?string $content = null,
    ): void {
        $mail = new AccountantAccessMail(
            ownerName: $owner->display_name ?: ($owner->email ?? 'A TaxVault user'),
            accountantName: $grant->name,
            link: $link,
            kind: $kind,
            yearLabel: $yearLabel,
            note: $note,
            attachmentData: $attachmentData,
            attachmentName: $attachmentName,
            fromEmail: $fromEmail,
            fromName: $fromName,
            subjectOverride: $subject,
            contentOverride: $content,
        );

        if ($attachmentData !== null && $attachmentName !== null) {
            $attachments = $attachments ?: [[
                'data' => $attachmentData,
                'name' => $attachmentName,
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]];
        }
        foreach ($attachments as $attachment) {
            $mail->attachData($attachment['data'], $attachment['name'], [
                'mime' => $attachment['mime'],
            ]);
        }

        Mail::to($toEmail ?: $grant->email)->send($mail);
    }

    private function trySendMail(
        AccountantGrant $grant,
        User $owner,
        string $kind,
        string $link,
        mixed ...$mailOptions,
    ): bool {
        try {
            $this->sendMail($grant, $owner, $kind, $link, ...$mailOptions);

            return true;
        } catch (\Throwable $exception) {
            Log::error('Accountant email delivery failed.', [
                'grant_id' => $grant->getKey(),
                'kind' => $kind,
                'exception' => $exception::class,
            ]);

            return false;
        }
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
