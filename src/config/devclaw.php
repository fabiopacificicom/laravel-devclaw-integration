<?php

return [
    /*
    |--------------------------------------------------------------------------
    | DevClaw Integration Enabled
    |--------------------------------------------------------------------------
    | Set to false to completely disable all DevClaw routes and middleware.
    | Recommended to disable in production if not actively using the integration.
    */
    'enabled' => env('DEVCLAW_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | DevClaw API Token
    |--------------------------------------------------------------------------
    | Bearer token required for all API requests. Generate with:
    |   openssl rand -hex 32
    | Set in .env as DEVCLAW_TOKEN
    */
    'token' => env('DEVCLAW_TOKEN', null),

    /*
    |--------------------------------------------------------------------------
    | Default AI Model
    |--------------------------------------------------------------------------
    | Override the default AI model used for chat requests.
    | Leave null to use the platform's own default.
    */
    'default_model' => env('DEVCLAW_DEFAULT_MODEL', null),

    /*
    |--------------------------------------------------------------------------
    | Allowed Tools
    |--------------------------------------------------------------------------
    | '*' allows all tools. Or provide a comma-separated list:
    |   DEVCLAW_ALLOWED_TOOLS=get_courses,get_stats
    */
    'allowed_tools' => env('DEVCLAW_ALLOWED_TOOLS', '*'),

    /*
    |--------------------------------------------------------------------------
    | Log Requests
    |--------------------------------------------------------------------------
    | Log all incoming DevClaw API requests to the Laravel log.
    */
    'log_requests' => env('DEVCLAW_LOG_REQUESTS', true),
];
