<?php

namespace App\Application\Documents;

use App\Domain\Documents\Models\DocumentType;
use App\Models\InboundEmailAddress;
use Illuminate\Http\UploadedFile;
use Throwable;

final class IngestInboundEmail
{
    private const MAX_BYTES = 20 * 1024 * 1024;

    public function __construct(private readonly UploadDocument $uploadDocument) {}

    /**
     * Postmark-style inbound payload. Returns counts of stored/skipped attachments.
     *
     * @param  array<string, mixed>  $payload
     * @return array{matched: bool, stored: int, skipped: int}
     */
    public function execute(array $payload): array
    {
        $address = $this->resolveAddress($payload);

        if ($address === null) {
            return ['matched' => false, 'stored' => 0, 'skipped' => 0];
        }

        $user = $address->user;
        $typeId = DocumentType::query()->where('status', 'active')->where('code', 'other')->value('id')
            ?? DocumentType::query()->where('status', 'active')->orderBy('sort_order')->value('id');

        $stored = 0;
        $skipped = 0;

        foreach ((array) ($payload['Attachments'] ?? []) as $attachment) {
            $content = is_array($attachment) && is_string($attachment['Content'] ?? null)
                ? base64_decode($attachment['Content'], true)
                : false;

            if ($typeId === null || $content === false || $content === '' || strlen($content) > self::MAX_BYTES) {
                $skipped++;

                continue;
            }

            $path = tempnam(sys_get_temp_dir(), 'tvmail');
            file_put_contents($path, $content);
            $name = basename((string) ($attachment['Name'] ?? 'attachment'));

            try {
                $this->uploadDocument->execute(
                    user: $user,
                    financialYearId: null,
                    documentTypeId: $typeId,
                    file: new UploadedFile($path, $name, null, null, true),
                    provenance: [
                        'source' => 'email_forward',
                        'from' => (string) ($payload['From'] ?? ''),
                        'subject' => (string) ($payload['Subject'] ?? ''),
                    ],
                );
                $stored++;
            } catch (Throwable) {
                $skipped++;
            } finally {
                @unlink($path);
            }
        }

        return ['matched' => true, 'stored' => $stored, 'skipped' => $skipped];
    }

    /** @param  array<string, mixed>  $payload */
    private function resolveAddress(array $payload): ?InboundEmailAddress
    {
        $recipients = [(string) ($payload['OriginalRecipient'] ?? '')];

        foreach ((array) ($payload['ToFull'] ?? []) as $to) {
            $recipients[] = (string) ($to['Email'] ?? '');
        }

        foreach ($recipients as $recipient) {
            $token = strtolower(strstr($recipient, '@', true) ?: '');

            if ($token === '') {
                continue;
            }

            $address = InboundEmailAddress::query()->with('user')->where('token', $token)->first();

            if ($address !== null && $address->user?->status === 'active') {
                return $address;
            }
        }

        return null;
    }
}
