<?php

namespace App\Reports\Extraction;

use RuntimeException;

class ExtractionFailed extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $stopReason = null,
        public readonly bool $retryable = false,
    ) {
        parent::__construct($message);
    }
}
