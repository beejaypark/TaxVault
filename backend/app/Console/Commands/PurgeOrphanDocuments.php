<?php

namespace App\Console\Commands;

use App\Application\Audit\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PurgeOrphanDocuments extends Command
{
    protected $signature = 'documents:purge-orphans
        {--force : Actually delete; without it only lists orphans}
        {--min-age-hours=24 : Ignore files newer than this (in-flight uploads)}';

    protected $description = 'Delete stored document files that no document record references';

    public function handle(AuditLogger $audit): int
    {
        $disk = Storage::disk('documents');
        $known = DB::table('documents')->where('storage_disk', 'documents')->pluck('object_key')->flip();
        $cutoff = now()->subHours((int) $this->option('min-age-hours'))->getTimestamp();
        $orphans = 0;

        foreach ($disk->allFiles() as $path) {
            if ($known->has($path) || $disk->lastModified($path) > $cutoff) {
                continue;
            }

            $orphans++;

            if ($this->option('force')) {
                $disk->delete($path);
                $audit->record(null, 'document.orphan_purged', 'document_file', null, ['path' => $path]);
            }
        }

        $this->info(sprintf('%s %d orphan file(s).', $this->option('force') ? 'Deleted' : 'Found', $orphans));

        return self::SUCCESS;
    }
}
