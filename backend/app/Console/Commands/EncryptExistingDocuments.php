<?php

namespace App\Console\Commands;

use App\Infrastructure\Storage\FileCipher;
use Illuminate\Console\Command;
use League\Flysystem\Local\LocalFilesystemAdapter;

class EncryptExistingDocuments extends Command
{
    protected $signature = 'documents:encrypt-existing {--dry-run : List plaintext files without changing them}';

    protected $description = 'Encrypt document files that were stored before encryption at rest was enabled';

    public function handle(): int
    {
        $raw = new LocalFilesystemAdapter((string) config('filesystems.disks.documents.root'));
        $cipher = FileCipher::fromConfig();
        $encrypted = 0;
        $already = 0;

        foreach ($raw->listContents('', true) as $item) {
            if (! $item->isFile()) {
                continue;
            }

            $contents = $raw->read($item->path());

            if (FileCipher::isEncrypted($contents)) {
                $already++;

                continue;
            }

            if (! $this->option('dry-run')) {
                $raw->write($item->path(), $cipher->encrypt($contents), new \League\Flysystem\Config);
            }

            $encrypted++;
        }

        $this->info(sprintf(
            '%s %d plaintext file(s); %d already encrypted.',
            $this->option('dry-run') ? 'Found' : 'Encrypted',
            $encrypted,
            $already,
        ));

        return self::SUCCESS;
    }
}
