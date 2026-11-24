<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Settings: per-group role permissions (override config/fgre_permissions.php),
 * an audit log of administrative changes, the user's last sign-in and theme.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('hotel_groups')->cascadeOnDelete();
            $table->string('role', 30)->comment('finance | operations | viewer (owner always has every permission)');
            $table->jsonb('permissions')->comment('permission names granted to this role in this group');
            $table->timestamps();

            $table->unique(['group_id', 'role']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('hotel_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->comment('who made the change');
            $table->string('action', 50)->comment('e.g. user.added, role.updated, hotel.updated');
            $table->string('subject')->nullable()->comment('what was changed, as shown to people (user email, hotel name …)');
            $table->jsonb('details')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['group_id', 'created_at']);
        });

        // Invitation links for new users (password broker "invites", 7 days).
        Schema::create('user_invite_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_login_at')->nullable()->after('locale');
            $table->string('theme', 6)->default('system')->after('locale')->comment('light | dark | system');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['last_login_at', 'theme']));
        Schema::dropIfExists('user_invite_tokens');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('group_roles');
    }
};
