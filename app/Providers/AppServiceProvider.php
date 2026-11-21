<?php

namespace App\Providers;

use Anthropic\Client as AnthropicClient;
use App\Models\User;
use App\Reports\Extraction\ClaudeReportExtractor;
use App\Reports\Extraction\ReportExtractor;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Key comes from config (not getenv) so it still works after `php artisan config:cache`.
        // With no key configured, the SDK falls back to its own credential chain.
        $this->app->singleton(AnthropicClient::class, fn () => new AnthropicClient(
            apiKey: config('fgre.ai.api_key') ?: null,
        ));

        // The extractor used for PDFs.
        $this->app->bind(ReportExtractor::class, ClaudeReportExtractor::class);
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
