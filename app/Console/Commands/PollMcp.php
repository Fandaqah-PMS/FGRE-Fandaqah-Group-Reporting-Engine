<?php

namespace App\Console\Commands;

use App\Ingestion\Mcp\McpReportPuller;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('fgre:poll-mcp')]
#[Description('Pull the last closed day\'s reports from PMS MCP servers for hotels set up for it')]
class PollMcp extends Command
{
    public function handle(McpReportPuller $puller): int
    {
        $stats = $puller->pullAll();

        $this->components->info(sprintf('Pulled: %d, already up to date: %d, failed: %d', $stats['pulled'], $stats['skipped'], $stats['failed']));

        return $stats['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
