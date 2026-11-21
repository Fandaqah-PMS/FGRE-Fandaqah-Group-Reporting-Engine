<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Received = 'received';
    case Extracting = 'extracting';
    case NeedsReview = 'needs_review';
    case Processed = 'processed';
    case Failed = 'failed';
    case Rejected = 'rejected';

    public function isFinal(): bool
    {
        return in_array($this, [self::Processed, self::Rejected], true);
    }
}
