<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbound_messages', function (Blueprint $table) {
            $table->id();
            $table->string('source', 20)->comment('email | mcp | upload | api');
            $table->string('external_id')->comment('email Message-ID, or pull/upload id');
            $table->string('from_email')->nullable()->index();
            $table->string('subject', 1000)->nullable();
            $table->timestampTz('received_at')->nullable();
            $table->unsignedSmallInteger('attachment_count')->default(0);
            $table->string('status', 20)->default('received')->comment('received | processed | ignored');
            $table->text('status_reason')->nullable();
            $table->timestamps();

            $table->unique(['source', 'external_id']);
        });

        Schema::create('report_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inbound_message_id')->nullable()->constrained()->nullOnDelete();
            // Null until the document is matched to a property.
            $table->foreignId('group_id')->nullable()->constrained('hotel_groups')->nullOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->string('report_type', 40)->default('unknown')->comment('App\Enums\ReportType');
            $table->date('period_start')->nullable()->comment('set for period reports (GL trial balance)');
            $table->date('business_date')->nullable()->comment('report date, or period end');
            $table->boolean('is_provisional')->default(false)->comment('PMS marked the day as not closed');
            $table->string('original_filename')->nullable();
            $table->string('storage_disk', 50);
            $table->string('storage_path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64)->unique()->comment('the same file is never processed twice');
            $table->string('status', 20)->default('received')->index()->comment('App\Enums\DocumentStatus');
            $table->text('status_reason')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->timestamps();

            $table->index(['property_id', 'report_type', 'business_date']);
        });

        Schema::create('extraction_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_document_id')->constrained()->cascadeOnDelete();
            $table->string('extractor', 40)->comment('pdf:manager_flash | pdf:guest_trial_balance | fandaqah_json …');
            $table->string('status', 30)->comment('App\Enums\ExtractionStatus');
            $table->jsonb('output')->nullable()->comment('canonical extraction output');
            $table->jsonb('validation_errors')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extraction_runs');
        Schema::dropIfExists('report_documents');
        Schema::dropIfExists('inbound_messages');
    }
};
