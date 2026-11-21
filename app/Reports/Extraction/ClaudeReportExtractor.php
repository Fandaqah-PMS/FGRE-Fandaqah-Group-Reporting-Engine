<?php

namespace App\Reports\Extraction;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\InternalServerException;
use Anthropic\Core\Exceptions\RateLimitException;
use Anthropic\Lib\Streaming\MessageAccumulator;
use App\Models\ReportDocument;

/**
 * Reads a report PDF with Claude and returns it in the ExtractionSchema shape.
 *
 * - The PDF goes in as a base64 `document` block; structured outputs constrain the reply to
 *   ExtractionSchema, so the JSON always parses and has every required key.
 * - Streams the response: a large trial balance can take minutes, and streaming
 *   avoids HTTP timeouts.
 * - Server-side fallbacks ("default") retry on a substitute model if a request is declined.
 */
class ClaudeReportExtractor implements ReportExtractor
{
    public function __construct(private readonly Client $client) {}

    /** PDFs go in as base64; text and JSON (e.g. from an MCP tool) as a plain-text document. */
    private function source(ReportDocument $document): array
    {
        return $document->mime_type === 'application/pdf'
            ? ['type' => 'base64', 'mediaType' => 'application/pdf', 'data' => base64_encode($document->contents())]
            : ['type' => 'text', 'mediaType' => 'text/plain', 'data' => $document->contents()];
    }

    public function extract(ReportDocument $document): ExtractionResult
    {
        $model = config('fgre.ai.model');

        try {
            $stream = $this->client->beta->messages->createStream(
                model: $model,
                maxTokens: (int) config('fgre.ai.max_tokens'),
                system: [
                    // Stable across all requests, so it is cached.
                    ['type' => 'text', 'text' => ExtractionSchema::systemPrompt(), 'cacheControl' => ['type' => 'ephemeral']],
                ],
                messages: [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'document', 'source' => $this->source($document)],
                        ['type' => 'text', 'text' => ExtractionSchema::userInstruction()],
                    ],
                ]],
                outputConfig: [
                    'effort' => config('fgre.ai.effort'),
                    'format' => ['type' => 'json_schema', 'schema' => ExtractionSchema::schema()],
                ],
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );

            $accumulator = MessageAccumulator::forBetaMessages();
            foreach ($stream as $event) {
                $accumulator->accumulate($event);
            }
            $message = $accumulator->message();
        } catch (RateLimitException|InternalServerException|APIConnectionException $e) {
            throw new ExtractionFailed('Claude API temporarily unavailable: '.$e->getMessage(), retryable: true);
        } catch (APIStatusException $e) {
            throw new ExtractionFailed('Claude API rejected the request: '.$e->getMessage());
        }

        $stopReason = $message->stopReason;

        if ($stopReason === 'refusal') {
            throw new ExtractionFailed('Claude declined to read this document.', $stopReason);
        }

        if ($stopReason === 'max_tokens') {
            throw new ExtractionFailed(
                'Output hit max_tokens before the report was fully extracted; raise FGRE_AI_MAX_TOKENS.',
                $stopReason,
            );
        }

        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        $output = json_decode($text, true);
        if (! is_array($output)) {
            throw new ExtractionFailed('Claude returned output that is not valid JSON.', $stopReason);
        }

        return new ExtractionResult(
            output: $output,
            extractor: 'claude',
            model: $message->model,
            stopReason: $stopReason,
            inputTokens: $message->usage->inputTokens,
            outputTokens: $message->usage->outputTokens,
        );
    }
}
