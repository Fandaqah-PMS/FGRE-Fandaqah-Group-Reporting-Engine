<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            // Hotel group. Becomes a FK once FGRE adopts the Fandaqah PMS tenancy model.
            $table->uuid('tenant_id')->index();
            $table->foreignId('pms_provider_id')->constrained('pms_providers');
            $table->string('name');
            $table->string('code', 50);                 // short code used in subjects/filenames
            $table->string('pms_property_ref')->nullable(); // property id inside the PMS
            $table->string('timezone')->default('Asia/Riyadh');
            $table->char('currency', 3)->default('SAR');
            $table->unsignedInteger('total_rooms')->nullable();
            // Only PDFs from these sender addresses are accepted for this property.
            $table->jsonb('allowed_sender_emails')->default('[]');
            // Extra names the PMS may print for this hotel (helps AI matching).
            $table->jsonb('aliases')->default('[]');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
