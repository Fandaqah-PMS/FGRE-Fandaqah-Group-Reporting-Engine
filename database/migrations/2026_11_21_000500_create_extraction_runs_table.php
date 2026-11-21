<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extraction_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_document_id')->constrained()->cascadeOnDelete();
            $table->string('model', 100);
            $table->string('prompt_version', 50);
            $table->string('status', 30);               // App\Enums\ExtractionStatus
            $table->jsonb('output')->nullable();        // raw structured output from the model
            $table->jsonb('validation_errors')->nullable();
            $table->text('error')->nullable();
            $table->string('stop_reason', 50)->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extraction_runs');
    }
};
