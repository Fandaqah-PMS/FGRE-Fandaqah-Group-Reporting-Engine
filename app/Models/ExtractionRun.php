<?php

namespace App\Models;

use App\Enums\ExtractionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtractionRun extends Model
{
    protected $fillable = [
        'report_document_id', 'model', 'prompt_version', 'status', 'output',
        'validation_errors', 'error', 'stop_reason', 'input_tokens', 'output_tokens', 'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'status' => ExtractionStatus::class,
            'output' => 'array',
            'validation_errors' => 'array',
        ];
    }

    public function reportDocument(): BelongsTo
    {
        return $this->belongsTo(ReportDocument::class);
    }
}
