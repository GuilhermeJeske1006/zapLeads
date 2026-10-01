<?php

namespace App\Providers;

use Anthropic\Client as AnthropicClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AnthropicClient::class, fn () => new AnthropicClient(apiKey: config('services.anthropic.key')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
