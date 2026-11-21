<?php

namespace App\Reports\Extraction;

final readonly class ExtractionResult
{
    /**
     * @param  array<string, mixed>  $output  data in the ExtractionSchema shape
     * @param  string  $extractor  which extractor produced it (claude | fandaqah_json)
     */
    public function __construct(
        public array $output,
        public string $extractor,
        public ?string $model = null,
        public ?string $stopReason = null,
        public ?int $inputTokens = null,
        public ?int $outputTokens = null,
    ) {}
}
