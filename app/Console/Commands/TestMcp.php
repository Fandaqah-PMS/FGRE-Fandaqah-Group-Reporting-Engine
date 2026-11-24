<?php

namespace App\Console\Commands;

use App\Ingestion\Mcp\McpClient;
use App\Models\McpConnection;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('fgre:mcp-test {connection : MCP connection id}')]
#[Description('Connect to a PMS MCP server and list its tools (to set up properties.mcp_settings)')]
class TestMcp extends Command
{
    public function handle(): int
    {
        $connection = McpConnection::withoutGlobalScopes()->find($this->argument('connection'));
        if ($connection === null) {
            $this->components->error('MCP connection not found.');

            return self::FAILURE;
        }

        try {
            $tools = (new McpClient($connection))->listTools();
        } catch (Throwable $e) {
            $this->components->error("Could not use [{$connection->url}]: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->components->info(count($tools)." tool(s) on {$connection->name}:");
        foreach ($tools as $tool) {
            $params = implode(', ', array_keys($tool['inputSchema']['properties'] ?? []));
            $this->components->twoColumnDetail("<fg=green>{$tool['name']}</>({$params})", (string) $tool['description']);
        }

        return self::SUCCESS;
    }
}
