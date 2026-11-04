<?php

namespace App\Console\Commands;

use App\Infrastructure\Storage\FileCipher;
use Illuminate\Console\Command;
use RuntimeException;

class VerifyDocumentFiles extends Command
{
    protected $signature = 'documents:verify {--root= : Directory to check (defaults to the documents disk root)}';

    protected $description = 'Check that every stored document file decrypts with the current key (e.g. after a restore)';

    public function handle(): int
    {
        $root = rtrim((string) ($this->option('root') ?: config('filesystems.disks.documents.root')), '/\\');

        if (! is_dir($root)) {
            $this->error("Directory not found: {$root}");

            return self::FAILURE;
        }

        $cipher = FileCipher::fromConfig();
        $ok = 0;
        $plaintext = 0;
        $failed = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $data = (string) file_get_contents($file->getPathname());

            if (! FileCipher::isEncrypted($data)) {
                $plaintext++;

                continue;
            }

            try {
                $cipher->decrypt($data);
                $ok++;
            } catch (RuntimeException) {
                $failed[] = substr($file->getPathname(), strlen($root) + 1);
            }
        }

        $this->info(sprintf('%d decrypted OK, %d plaintext, %d failed.', $ok, $plaintext, count($failed)));

        foreach ($failed as $path) {
            $this->line("  FAILED: {$path}");
        }

        return $failed === [] ? self::SUCCESS : self::FAILURE;
    }
}
