<?php

namespace App\Enums;

enum ExtractionStatus: string
{
    case Running = 'running';
    case Succeeded = 'succeeded';
    case ValidationFailed = 'validation_failed';
    case Failed = 'failed';
}
