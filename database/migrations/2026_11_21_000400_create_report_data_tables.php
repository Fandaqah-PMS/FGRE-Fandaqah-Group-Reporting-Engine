<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Canonical, PMS-independent report data. Dashboards and group reports read
 * only these tables.
 *
 * Stats store the *parts* (rooms, revenue). Headline KPIs (occupancy, ADR,
 * RevPAR) are always recomputed from parts by App\Reports\Kpi\KpiCalculator,
 * so group and multi-day figures are correct. Printed KPI values are kept
 * only for reference and validation (printed_* columns).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_property_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('hotel_groups')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_document_id')->constrained()->cascadeOnDelete();
            $table->date('business_date');
            $table->string('period', 8)->comment('day | mtd | ytd | day_ly | mtd_ly | ytd_ly');
            $table->boolean('is_provisional')->default(false);
            $table->char('currency', 3);

            // Room-nights for the period.
            $table->unsignedInteger('total_rooms')->nullable()->comment('rooms in hotel (inventory)');
            $table->unsignedInteger('rooms_occupied')->nullable()->comment('incl. comp and house use, excl. day use');
            $table->unsignedInteger('ooo_rooms')->nullable()->comment('out of order: removed from sellable inventory');
            $table->unsignedInteger('oos_rooms')->nullable()->comment('out of service: reported only');
            $table->unsignedInteger('comp_rooms')->nullable();
            $table->unsignedInteger('house_use_rooms')->nullable();
            $table->unsignedInteger('day_use_rooms')->nullable();

            // Revenue, net of taxes when the PMS reports net.
            $table->decimal('room_revenue', 18, 2)->nullable();
            $table->decimal('fnb_revenue', 18, 2)->nullable();
            $table->decimal('other_revenue', 18, 2)->nullable();
            $table->decimal('total_revenue', 18, 2)->nullable();

            // Activity.
            $table->unsignedInteger('persons_in_house')->nullable();
            $table->unsignedInteger('arrival_rooms')->nullable();
            $table->unsignedInteger('departure_rooms')->nullable();
            $table->unsignedInteger('no_show_rooms')->nullable();
            $table->unsignedInteger('cancellations')->nullable();

            // As printed by the PMS (definitions differ per PMS). Reference only.
            $table->decimal('printed_occupancy_pct', 7, 2)->nullable();
            $table->decimal('printed_adr', 18, 2)->nullable();
            $table->decimal('printed_revpar', 18, 2)->nullable();
            $table->timestamps();

            $table->unique(['property_id', 'business_date', 'period']);
            $table->index(['group_id', 'business_date', 'period']);
        });

        Schema::create('revenue_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('hotel_groups')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_document_id')->constrained()->cascadeOnDelete();
            $table->date('business_date');
            $table->string('period', 8);
            $table->string('department')->comment('as printed');
            $table->string('category', 10)->comment('rooms | fnb | other');
            $table->decimal('amount', 18, 2);
            $table->char('currency', 3);
            $table->timestamps();

            $table->index(['property_id', 'business_date', 'period']);
        });

        // General-ledger trial balance (e.g. Fandaqah FST-001). May cover a period.
        Schema::create('trial_balance_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('hotel_groups')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_document_id')->constrained()->cascadeOnDelete();
            $table->date('period_start')->nullable();
            $table->date('business_date')->comment('period end');
            $table->string('account_code', 50)->comment('standard account no. as printed (Fandaqah: NTMP)');
            $table->string('local_account_code', 50)->nullable()->comment("hotel's own number, if printed separately");
            $table->string('account_name');
            $table->string('account_class', 50)->nullable()->comment('as printed: Assets, Income ...');
            $table->decimal('opening_balance', 18, 2)->nullable()->comment('signed: debit +, credit -');
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            $table->decimal('closing_balance', 18, 2)->nullable()->comment('signed: debit +, credit -');
            $table->char('currency', 3);
            $table->timestamps();

            // One standard account can appear under several local accounts.
            $table->unique(['property_id', 'business_date', 'account_code', 'local_account_code']);
            $table->index(['group_id', 'business_date']);
        });

        // Front-office (ledger) trial balance, Opera style (e.g. Fandaqah FIN-040).
        Schema::create('ledger_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('hotel_groups')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_document_id')->constrained()->cascadeOnDelete();
            $table->date('business_date');
            $table->string('ledger', 10)->comment('guest | ar | deposit | package | other');
            $table->decimal('opening_balance', 18, 2)->nullable();
            $table->decimal('closing_balance', 18, 2);
            $table->char('currency', 3);
            $table->timestamps();

            $table->unique(['property_id', 'business_date', 'ledger']);
        });

        Schema::create('transaction_code_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('hotel_groups')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_document_id')->constrained()->cascadeOnDelete();
            $table->date('business_date');
            $table->string('code', 20)->nullable();
            $table->string('description');
            $table->string('category', 12)->comment('revenue | non_revenue | paid_out | payment | other');
            $table->decimal('amount', 18, 2);
            $table->char('currency', 3);
            $table->timestamps();

            $table->index(['property_id', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_code_lines');
        Schema::dropIfExists('ledger_balances');
        Schema::dropIfExists('trial_balance_lines');
        Schema::dropIfExists('revenue_lines');
        Schema::dropIfExists('daily_property_stats');
    }
};
