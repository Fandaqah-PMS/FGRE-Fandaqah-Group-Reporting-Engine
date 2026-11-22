<?php

namespace App\Reports\Extraction;

final readonly class ExtractionResult
{
    /**
     * @param  array<string, mixed>  $output  data in the ExtractionSchema shape
     * @param  string  $extractor  which reader produced it (pdf:manager_flash, fandaqah_json …)
     */
    public function __construct(
        public array $output,
        public string $extractor,
    ) {}
}
