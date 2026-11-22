<?php

namespace App\Reports\Extraction;

use RuntimeException;

/** A document could not be read. Retryable failures (tool missing, timeout) are tried again by the queue. */
class ExtractionFailed extends RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable = false)
    {
        parent::__construct($message);
    }
}
