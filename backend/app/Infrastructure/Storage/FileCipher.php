<?php

namespace App\Infrastructure\Storage;

use RuntimeException;

/**
 * AES-256-GCM file format: "TVENC1" | salt(16) | nonce(12) | ciphertext | tag(16).
 * A per-file key is derived from the master key with HKDF, so no key is ever
 * reused across files.
 */
final class FileCipher
{
    public const MAGIC = 'TVENC1';

    private const SALT_BYTES = 16;

    private const NONCE_BYTES = 12;

    private const TAG_BYTES = 16;

    private readonly string $masterKey;

    public function __construct(string $masterKey)
    {
        if (strlen($masterKey) !== 32) {
            throw new RuntimeException('The document encryption key must be 32 bytes.');
        }

        $this->masterKey = $masterKey;
    }

    /** Uses DOCUMENT_ENCRYPTION_KEY (base64, 32 bytes) or derives a key from APP_KEY. */
    public static function fromConfig(): self
    {
        $configured = config('filesystems.document_encryption_key');

        if (is_string($configured) && $configured !== '') {
            $key = base64_decode(str_starts_with($configured, 'base64:') ? substr($configured, 7) : $configured, true);

            if ($key === false || strlen($key) !== 32) {
                throw new RuntimeException('DOCUMENT_ENCRYPTION_KEY must be base64 of exactly 32 bytes.');
            }

            return new self($key);
        }

        $appKey = (string) config('app.key');

        if ($appKey === '') {
            throw new RuntimeException('Neither DOCUMENT_ENCRYPTION_KEY nor APP_KEY is configured.');
        }

        return new self(hash_hkdf('sha256', $appKey, 32, 'taxvault-document-encryption'));
    }

    public static function isEncrypted(string $data): bool
    {
        return str_starts_with($data, self::MAGIC);
    }

    public function encrypt(string $plaintext): string
    {
        $salt = random_bytes(self::SALT_BYTES);
        $nonce = random_bytes(self::NONCE_BYTES);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $this->fileKey($salt),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            self::MAGIC,
            self::TAG_BYTES,
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Unable to encrypt the document.');
        }

        return self::MAGIC.$salt.$nonce.$ciphertext.$tag;
    }

    /** Legacy plaintext files (no header) are returned unchanged until migrated. */
    public function decrypt(string $data): string
    {
        if (! self::isEncrypted($data)) {
            return $data;
        }

        $minimum = strlen(self::MAGIC) + self::SALT_BYTES + self::NONCE_BYTES + self::TAG_BYTES;

        if (strlen($data) < $minimum) {
            throw new RuntimeException('Encrypted document is truncated.');
        }

        $offset = strlen(self::MAGIC);
        $salt = substr($data, $offset, self::SALT_BYTES);
        $nonce = substr($data, $offset + self::SALT_BYTES, self::NONCE_BYTES);
        $tag = substr($data, -self::TAG_BYTES);
        $ciphertext = substr($data, $offset + self::SALT_BYTES + self::NONCE_BYTES, -self::TAG_BYTES);

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $this->fileKey($salt),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            self::MAGIC,
        );

        if ($plaintext === false) {
            throw new RuntimeException('Unable to decrypt the document: wrong key or tampered data.');
        }

        return $plaintext;
    }

    private function fileKey(string $salt): string
    {
        return hash_hkdf('sha256', $this->masterKey, 32, 'taxvault-file', $salt);
    }
}
