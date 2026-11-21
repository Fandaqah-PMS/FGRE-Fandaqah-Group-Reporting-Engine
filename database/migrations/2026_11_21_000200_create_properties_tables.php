<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pms_providers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('fandaqah | nazeel | opera');
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('hotel_groups')->cascadeOnDelete();
            $table->foreignId('pms_provider_id')->constrained('pms_providers');
            $table->string('name')->comment('English name, as the PMS prints it');
            $table->string('name_ar')->nullable()->comment('Arabic name, as the PMS prints it');
            $table->string('code', 50)->comment('FGRE short code, unique per group');
            $table->string('pms_property_ref')->nullable()->comment('property id inside the PMS (Fandaqah: teams.id)');
            $table->string('timezone', 64)->default('Asia/Riyadh');
            $table->char('currency', 3)->default('SAR');
            $table->unsignedInteger('total_rooms')->nullable();
            $table->jsonb('allowed_sender_emails')->default('[]')
                ->comment('reports are accepted only from these senders');
            $table->jsonb('aliases')->default('[]')
                ->comment('other names the PMS prints or puts in subjects for this hotel');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['group_id', 'code']);
        });

        Schema::create('property_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['property_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_user');
        Schema::dropIfExists('properties');
        Schema::dropIfExists('pms_providers');
    }
};
