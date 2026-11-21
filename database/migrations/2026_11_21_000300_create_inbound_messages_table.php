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
            $table->string('source', 20);               // App\Enums\InboundSource
            $table->string('external_id');              // email Message-ID, or MCP pull id
            $table->string('from_email')->nullable()->index();
            $table->string('subject', 1000)->nullable();
            $table->timestampTz('received_at')->nullable();
            $table->unsignedSmallInteger('attachment_count')->default(0);
            $table->string('status', 20)->default('received'); // received | processed | ignored
            $table->text('status_reason')->nullable();
            $table->timestamps();

            $table->unique(['source', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbound_messages');
    }
};
