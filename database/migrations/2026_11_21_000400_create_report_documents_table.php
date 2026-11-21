<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inbound_message_id')->nullable()->constrained()->nullOnDelete();
            // Unknown until the document is matched to a property.
            $table->uuid('tenant_id')->nullable()->index();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->string('report_type', 30)->default('unknown'); // App\Enums\ReportType
            $table->date('business_date')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('storage_disk', 50);
            $table->string('storage_path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64)->unique();       // same bytes are never processed twice
            $table->string('status', 20)->default('received')->index(); // App\Enums\DocumentStatus
            $table->text('status_reason')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->timestamps();

            $table->index(['property_id', 'report_type', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_documents');
    }
};
