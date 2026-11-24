<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner tools: hotel sets (a region or cluster per hotel, for subtotals), monthly
 * budgets per hotel (USALI rooms statistics + departmental revenue).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('region', 60)->nullable()->after('code')
                ->comment('hotel set for subtotals, e.g. Western region');
        });

        Schema::create('budget_months', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('hotel_groups')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->date('month')->comment('first day of the month');
            $table->unsignedInteger('rooms_sold')->comment('paid room nights');
            $table->decimal('room_revenue', 18, 2);
            $table->decimal('fnb_revenue', 18, 2)->default(0);
            $table->decimal('other_revenue', 18, 2)->default(0);
            $table->decimal('total_revenue', 18, 2);
            $table->timestamps();

            $table->unique(['property_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_months');
        Schema::table('properties', fn (Blueprint $table) => $table->dropColumn('region'));
    }
};
