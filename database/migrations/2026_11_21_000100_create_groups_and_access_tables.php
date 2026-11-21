<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenancy: a hotel group (the FGRE customer) owns several properties.
 * Users belong to one or more groups with a role, optionally limited to
 * some of the group's properties.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('slug', 100)->unique();
            $table->char('currency', 3)->default('SAR');
            $table->string('timezone', 64)->default('Asia/Riyadh');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_group_id')->nullable()->after('email')
                ->constrained('hotel_groups')->nullOnDelete();
            $table->boolean('is_platform_admin')->default(false)->after('current_group_id')
                ->comment('Fandaqah staff: manage groups, review unmatched documents');
            $table->string('locale', 2)->default('ar')->after('is_platform_admin');
        });

        Schema::create('group_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('hotel_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 30)->comment('owner | finance | operations | viewer (config/fgre_permissions.php)');
            $table->boolean('all_properties')->default(true)->comment('false = only properties in property_user');
            $table->timestamps();

            $table->unique(['group_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_user');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_group_id');
            $table->dropColumn(['is_platform_admin', 'locale']);
        });
        Schema::dropIfExists('hotel_groups');
    }
};
