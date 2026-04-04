<?php

namespace Pacificdev\DevClaw;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;

class DevClawServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/config/devclaw.php',
            'devclaw'
        );
    }

    public function boot(): void
    {
        // Publish config
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/config/devclaw.php' => config_path('devclaw.php'),
            ], 'devclaw-config');
        }

        // Register middleware alias
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('devclaw.auth', Http\Middleware\DevClawAuth::class);

        // Load routes only if enabled
        if (config('devclaw.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/routes/api.php');
        }
    }
}
