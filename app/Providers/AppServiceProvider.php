<?php

namespace App\Providers;

use Anthropic\Client as AnthropicClient;
use App\Reports\Extraction\ClaudeReportExtractor;
use App\Reports\Extraction\ReportExtractor;
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

        $this->app->bind(ReportExtractor::class, ClaudeReportExtractor::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
