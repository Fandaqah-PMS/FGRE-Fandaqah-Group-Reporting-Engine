<?php

namespace App\Ingestion\Mcp;

use App\Models\McpConnection;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimal MCP client over Streamable HTTP (JSON-RPC 2.0): initialize,
 * tools/list and tools/call. Accepts plain JSON or SSE responses.
 */
class McpClient
{
    public const PROTOCOL_VERSION = '2025-06-18';

    private ?string $sessionId = null;

    private int $nextId = 1;

    private bool $initialized = false;

    public function __construct(private readonly McpConnection $connection) {}

    /** @return list<array{name: string, description: ?string, inputSchema: array<string, mixed>}> */
    public function listTools(): array
    {
        $tools = [];
        $cursor = null;
        do {
            $result = $this->request('tools/list', $cursor === null ? [] : ['cursor' => $cursor]);
            foreach ($result['tools'] ?? [] as $tool) {
                $tools[] = [
                    'name' => $tool['name'],
                    'description' => $tool['description'] ?? null,
                    'inputSchema' => $tool['inputSchema'] ?? [],
                ];
            }
            $cursor = $result['nextCursor'] ?? null;
        } while ($cursor !== null);

        return $tools;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed> the tool result (content, structuredContent, isError)
     */
    public function callTool(string $name, array $arguments): array
    {
        $result = $this->request('tools/call', ['name' => $name, 'arguments' => (object) $arguments]);

        if ($result['isError'] ?? false) {
            $text = collect($result['content'] ?? [])->where('type', 'text')->pluck('text')->implode(' ');
            throw new RuntimeException("MCP tool [{$name}] returned an error: ".($text ?: 'no details'));
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function request(string $method, array $params): array
    {
        $this->initialize();

        return $this->send($method, $params);
    }

    private function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->send('initialize', [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities' => (object) [],
            'clientInfo' => ['name' => 'fgre', 'version' => '1.0'],
        ]);
        $this->notify('notifications/initialized');
        $this->initialized = true;
    }

    /** @return array<string, mixed> */
    private function send(string $method, array $params): array
    {
        $id = $this->nextId++;
        $response = $this->post(['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => (object) $params]);

        $this->sessionId ??= $response->header('Mcp-Session-Id') ?: null;

        $message = $this->findMessage($response, $id);
        if (isset($message['error'])) {
            throw new RuntimeException(sprintf('MCP %s failed: %s', $method, $message['error']['message'] ?? 'unknown error'));
        }

        return $message['result'] ?? [];
    }

    private function notify(string $method): void
    {
        $this->post(['jsonrpc' => '2.0', 'method' => $method]);
    }

    /** @param array<string, mixed> $payload */
    private function post(array $payload): Response
    {
        $headers = [
            'Accept' => 'application/json, text/event-stream',
            'MCP-Protocol-Version' => self::PROTOCOL_VERSION,
            ...$this->connection->authHeaders(),
        ];
        if ($this->sessionId !== null) {
            $headers['Mcp-Session-Id'] = $this->sessionId;
        }

        $response = Http::withHeaders($headers)->timeout(120)->asJson()->post($this->connection->url, $payload);

        if ($response->failed()) {
            throw new RuntimeException("MCP server answered HTTP {$response->status()}.");
        }

        return $response;
    }

    /** @return array<string, mixed> the JSON-RPC message answering $id */
    private function findMessage(Response $response, int $id): array
    {
        $body = $response->body();

        if (str_contains((string) $response->header('Content-Type'), 'text/event-stream')) {
            foreach (preg_split('/\r?\n\r?\n/', $body) as $event) {
                $data = collect(preg_split('/\r?\n/', $event))
                    ->filter(fn (string $line) => str_starts_with($line, 'data:'))
                    ->map(fn (string $line) => ltrim(substr($line, 5)))
                    ->implode("\n");
                $message = json_decode($data, true);
                if (is_array($message) && ($message['id'] ?? null) === $id) {
                    return $message;
                }
            }

            throw new RuntimeException('MCP server sent no answer to the request.');
        }

        $message = json_decode($body, true);
        if (! is_array($message)) {
            throw new RuntimeException('MCP server sent a response that is not JSON.');
        }

        return $message;
    }
}
