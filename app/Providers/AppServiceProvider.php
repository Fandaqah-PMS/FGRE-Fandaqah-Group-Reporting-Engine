<?php

namespace App\Providers;

use App\Models\User;
use App\Reports\Extraction\PdfReportExtractor;
use App\Reports\Extraction\ReportExtractor;
use App\Support\RolePermissions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // PDFs are read by layout readers on the server (pdftotext), never by AI.
        $this->app->bind(ReportExtractor::class, PdfReportExtractor::class);

        // Role permissions per group (config defaults + group overrides), cached per request.
        $this->app->singleton(RolePermissions::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // One Gate ability per permission (config/fgre_permissions.php), checked in the
        // user's current group. No super-user bypass.
        foreach (array_keys(config('fgre_permissions.permissions')) as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasGroupPermission($permission));
        }

        foreach (array_keys(config('fgre_permissions.platform_permissions')) as $permission) {
            Gate::define($permission, fn (User $user) => $user->is_platform_admin);
        }
    }
}
