<?php

use Illuminate\Support\Facades\Route;
use Pacificdev\DevClaw\Http\Controllers\DevClawController;

Route::prefix('api/devclaw')
    ->middleware(['api', 'devclaw.auth'])
    ->group(function () {

        // Health check
        Route::get('health', [DevClawController::class, 'health']);

        // Send a message to the admin AI agent
        Route::post('chat', [DevClawController::class, 'chat']);

        // List available tools
        Route::get('tools', [DevClawController::class, 'tools']);

        // Execute a specific tool directly
        Route::post('tools/{tool}', [DevClawController::class, 'executeTool']);

        // List courses (read-only)
        Route::get('courses', [DevClawController::class, 'courses']);

        // Platform stats
        Route::get('stats', [DevClawController::class, 'stats']);
    });
