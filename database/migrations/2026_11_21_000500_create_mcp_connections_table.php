<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional MCP sources: a PMS that runs an MCP server can be read directly
 * instead of (or as well as) by email. One connection can serve several hotels;
 * each hotel says which tool returns which report (properties.mcp_settings).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('hotel_groups')->cascadeOnDelete();
            $table->foreignId('pms_provider_id')->nullable()->constrained('pms_providers')->nullOnDelete();
            $table->string('name');
            $table->string('url')->comment('MCP Streamable HTTP endpoint');
            $table->string('auth_type', 10)->default('bearer')->comment('none | bearer | header');
            $table->string('auth_header')->nullable()->comment('header name when auth_type = header');
            $table->text('secret')->nullable()->comment('encrypted token / key');
            $table->boolean('is_active')->default(true);
            $table->timestampTz('last_polled_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->foreignId('mcp_connection_id')->nullable()->after('pms_property_ref')
                ->constrained('mcp_connections')->nullOnDelete();
            $table->jsonb('mcp_settings')->nullable()->after('mcp_connection_id')
                ->comment('{"reports": {"manager_flash": {"tool": "...", "arguments": {...}}}}');
            $table->string('night_audit_time', 5)->default('04:00')->after('timezone')
                ->comment('local time after which the previous business date is pulled');
        });

        Schema::table('inbound_messages', function (Blueprint $table) {
            $table->foreignId('property_id')->nullable()->after('source')
                ->constrained()->nullOnDelete()
                ->comment('known when the source is hotel-specific (MCP pull); email leaves it null');
        });
    }

    public function down(): void
    {
        Schema::table('inbound_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('property_id');
        });
        Schema::table('properties', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mcp_connection_id');
            $table->dropColumn(['mcp_settings', 'night_audit_time']);
        });
        Schema::dropIfExists('mcp_connections');
    }
};
