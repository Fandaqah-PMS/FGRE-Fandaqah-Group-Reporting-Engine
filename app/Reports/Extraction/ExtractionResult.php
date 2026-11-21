<?php

namespace App\Reports\Extraction;

final readonly class ExtractionResult
{
    /**
     * @param  array<string, mixed>  $output  data in the ExtractionSchema shape
     */
    public function __construct(
        public array $output,
        public string $model,
        public ?string $stopReason = null,
        public ?int $inputTokens = null,
        public ?int $outputTokens = null,
    ) {}
}
