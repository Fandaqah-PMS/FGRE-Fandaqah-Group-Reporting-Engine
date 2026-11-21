<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Canonical (PMS-independent) report data. Dashboards and group reports read
 * only from these tables, never from PMS-specific shapes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_property_stats', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_document_id')->constrained()->cascadeOnDelete();
            $table->date('business_date');
            $table->string('period', 3);                // App\Enums\StatPeriod
            $table->char('currency', 3);

            $table->unsignedInteger('rooms_available')->nullable();
            $table->unsignedInteger('rooms_out_of_order')->nullable();
            $table->unsignedInteger('rooms_sold')->nullable();
            $table->unsignedInteger('complimentary_rooms')->nullable();
            $table->unsignedInteger('house_use_rooms')->nullable();
            $table->decimal('occupancy_pct', 7, 2)->nullable();
            $table->decimal('adr', 18, 2)->nullable();
            $table->decimal('revpar', 18, 2)->nullable();

            $table->decimal('room_revenue', 18, 2)->nullable();
            $table->decimal('fnb_revenue', 18, 2)->nullable();
            $table->decimal('other_revenue', 18, 2)->nullable();
            $table->decimal('total_revenue', 18, 2)->nullable();

            $table->unsignedInteger('guests_in_house')->nullable();
            $table->unsignedInteger('arrivals')->nullable();
            $table->unsignedInteger('departures')->nullable();
            $table->unsignedInteger('no_shows')->nullable();
            $table->unsignedInteger('cancellations')->nullable();
            $table->timestamps();

            $table->unique(['property_id', 'business_date', 'period']);
            $table->index(['tenant_id', 'business_date']);
        });

        Schema::create('revenue_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_document_id')->constrained()->cascadeOnDelete();
            $table->date('business_date');
            $table->string('period', 3);
            $table->string('department');               // as printed: "Rooms", "Restaurant", "Laundry" ...
            $table->string('category', 20)->default('other'); // rooms | fnb | other
            $table->decimal('amount', 18, 2);
            $table->char('currency', 3);
            $table->timestamps();

            $table->index(['property_id', 'business_date', 'period']);
        });

        Schema::create('trial_balance_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_document_id')->constrained()->cascadeOnDelete();
            $table->date('business_date');
            $table->string('account_code', 100);
            $table->string('account_name');
            $table->string('account_type', 50)->nullable(); // as printed, if the PMS prints one
            $table->decimal('opening_balance', 18, 2)->nullable();
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            $table->decimal('closing_balance', 18, 2)->nullable();
            $table->char('currency', 3);
            $table->timestamps();

            $table->unique(['property_id', 'business_date', 'account_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trial_balance_lines');
        Schema::dropIfExists('revenue_lines');
        Schema::dropIfExists('daily_property_stats');
    }
};
