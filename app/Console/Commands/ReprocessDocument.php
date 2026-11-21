<?php

namespace App\Console\Commands;

use App\Enums\DocumentStatus;
use App\Jobs\ProcessReportDocument;
use App\Models\ReportDocument;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('fgre:reprocess {document : Report document id}')]
#[Description('Run AI extraction again for a report document (e.g. after fixing a property alias)')]
class ReprocessDocument extends Command
{
    public function handle(): int
    {
        $document = ReportDocument::find($this->argument('document'));
        if ($document === null) {
            $this->components->error('Report document not found.');

            return self::FAILURE;
        }

        $document->markStatus(DocumentStatus::Received);
        ProcessReportDocument::dispatch($document->id);

        $this->components->info("Report document #{$document->id} queued for processing.");

        return self::SUCCESS;
    }
}
