<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guest trial balance lines (folio balances per guest / company account) and
 * email subscriptions (daily / weekly / monthly reports by email).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_ledger_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('hotel_groups')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_document_id')->constrained()->cascadeOnDelete();
            $table->date('business_date');
            $table->string('room', 30)->nullable()->comment('room or account number as printed');
            $table->string('guest_name')->nullable()->comment('guest or company as printed (personal data)');
            $table->date('arrival')->nullable();
            $table->date('departure')->nullable();
            $table->decimal('opening_balance', 18, 2)->default(0);
            $table->decimal('debit', 18, 2)->default(0)->comment('debits + transfer debits');
            $table->decimal('credit', 18, 2)->default(0)->comment('credits + transfer credits');
            $table->decimal('closing_balance', 18, 2)->default(0)->comment('positive = guest owes the hotel');
            $table->string('status', 12)->comment('in_house | checked_out | other');
            $table->char('currency', 3);
            $table->timestamps();

            $table->index(['property_id', 'business_date']);
        });

        Schema::create('report_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('hotel_groups')->cascadeOnDelete();
            $table->string('report', 30)->default('manager_flash');
            $table->string('frequency', 10)->comment('daily | weekly | monthly');
            $table->string('send_time', 5)->default('08:00')->comment('local time (group timezone)');
            $table->unsignedTinyInteger('weekday')->nullable()->comment('weekly: 0 = Sunday … 6 = Saturday');
            $table->unsignedTinyInteger('month_day')->nullable()->comment('monthly: 1–28');
            $table->string('content', 10)->default('both')->comment('charts | tables | both');
            $table->jsonb('hotels')->nullable()->comment('property ids; null = all the user may see');
            $table->boolean('include_attention')->default(true);
            $table->char('locale', 2)->default('ar');
            $table->boolean('is_active')->default(true);
            $table->string('last_period_key', 30)->nullable()->comment('period already sent, e.g. D2026-11-20');
            $table->timestampTz('last_sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_subscriptions');
        Schema::dropIfExists('guest_ledger_lines');
    }
};
