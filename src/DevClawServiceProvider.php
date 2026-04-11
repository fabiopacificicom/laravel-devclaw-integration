<?php

namespace Pacificdev\DevClaw;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;
use Livewire\Livewire;

class DevClawServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/config/devclaw.php',
            'devclaw'
        );

        $this->app->singleton('devclaw.tool_executor', function ($app) {
            return new \Pacificdev\DevClaw\Services\ToolExecutor($app);
        });
    }

    public function boot(): void
    {
        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        // Load views
        $this->loadViewsFrom(__DIR__ . '/resources/views', 'pacificdev-devclaw');

        // Publish config
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/config/devclaw.php' => config_path('devclaw.php'),
            ], 'devclaw-config');

            $this->publishes([
                __DIR__ . '/resources/views' => resource_path('views/vendor/pacificdev-devclaw'),
            ], 'devclaw-views');

            $this->publishes([
                __DIR__ . '/database/migrations' => database_path('migrations'),
            ], 'devclaw-migrations');
        }

        // Register middleware alias
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('devclaw.auth', Http\Middleware\DevClawAuth::class);

        // Load routes only if enabled
        if (config('devclaw.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/routes/api.php');
        }

        // Register Livewire component
        if (class_exists(Livewire::class)) {
            Livewire::component('content-calendar', \Pacificdev\DevClaw\Livewire\ContentCalendar::class);
        }
    }
}
