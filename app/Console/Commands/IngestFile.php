<?php

namespace App\Console\Commands;

use App\Enums\InboundSource;
use App\Models\InboundMessage;
use App\Reports\IngestReportFile;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use InvalidArgumentException;

#[Signature('fgre:ingest-file {path : Path to a report PDF} {--from= : Sender email to match the hotel by, as if the file had been emailed}')]
#[Description('Ingest a report PDF from disk (for testing with sample reports)')]
class IngestFile extends Command
{
    public function handle(IngestReportFile $ingest): int
    {
        $path = $this->argument('path');
        if (! is_file($path)) {
            $this->components->error("File not found: {$path}");

            return self::FAILURE;
        }

        $message = InboundMessage::create([
            'source' => InboundSource::Upload,
            'external_id' => (string) Str::uuid(),
            'from_email' => $this->option('from'),
            'subject' => basename($path),
            'received_at' => now(),
            'attachment_count' => 1,
            'status' => 'processed',
        ]);

        try {
            $document = $ingest(file_get_contents($path), basename($path), $message);
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($document === null) {
            $this->components->warn('This exact file was already ingested.');

            return self::SUCCESS;
        }

        $this->components->info("Queued as report document #{$document->id}. Run `php artisan queue:work` to process it.");

        return self::SUCCESS;
    }
}
