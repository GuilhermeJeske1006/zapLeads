<?php

namespace App\Providers;

use Anthropic\Client as AnthropicClient;
use App\Services\Costs\UsageMeter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AnthropicClient::class, fn () => new AnthropicClient(apiKey: config('services.anthropic.key')));

        // Its context (empresa, search, lead) must not leak from one queued job to the next.
        $this->app->scoped(UsageMeter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
