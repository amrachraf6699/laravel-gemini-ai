<?php

namespace Amrachraf6699\LaravelGeminiAi\Providers;

use Amrachraf6699\LaravelGeminiAi\Services\GeminiAiService;
use Illuminate\Support\ServiceProvider;

class GeminiAiServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__.'/../../config/gemini.php', 'gemini'
        );

        // Register the service
        $this->app->singleton('gemini-ai', function ($app) {
            return new GeminiAiService();
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // Publish config
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/gemini.php' => config_path('gemini.php'),
            ], 'gemini-config');
        }
    }
}