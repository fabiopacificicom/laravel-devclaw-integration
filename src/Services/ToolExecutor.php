<?php

namespace Pacificdev\DevClaw\Services;

use Illuminate\Contracts\Container\Container;

class ToolExecutor
{
    public function __construct(protected Container $app)
    {
    }

    /**
     * Execute a named tool with the given arguments.
     *
     * Resolution order:
     *  1. Any class bound as `devclaw.platform_tools` in the container
     *  2. App\Services\AgentToolHandler
     *  3. App\Services\ToolHandler
     *  4. App\Http\Controllers\Admin\AgentController
     *
     * Call order per handler:
     *  1. $handler->executeTool($toolName, $arguments)
     *  2. $handler->$toolName($arguments)
     *  3. Invokable (container resolution of $toolName)
     *
     * @param  string  $toolName
     * @param  mixed   $arguments
     * @return mixed
     *
     * @throws \RuntimeException
     */
    public function execute(string $toolName, mixed $arguments): mixed
    {
        // Verify the tool exists in functions.json (soft check — warn but proceed)
        $this->assertToolKnown($toolName);

        $handler = $this->resolveHandler();

        if ($handler !== null) {
            return $this->callOnHandler($handler, $toolName, $arguments);
        }

        throw new \RuntimeException(
            "ToolExecutor: could not resolve a tool handler for tool '{$toolName}'. " .
            "Bind 'devclaw.platform_tools' in your AppServiceProvider or create one of: " .
            "App\\Services\\AgentToolHandler, App\\Services\\ToolHandler, " .
            "App\\Http\\Controllers\\Admin\\AgentController."
        );
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Try to resolve a handler instance from the host application.
     */
    private function resolveHandler(): mixed
    {
        // 1. Explicit container binding from host app
        if ($this->app->bound('devclaw.platform_tools')) {
            return $this->app->make('devclaw.platform_tools');
        }

        // 2–4. Well-known class candidates
        $candidates = [
            'App\\Services\\AgentToolHandler',
            'App\\Services\\ToolHandler',
            'App\\Http\\Controllers\\Admin\\AgentController',
        ];

        foreach ($candidates as $class) {
            if (class_exists($class)) {
                return $this->app->make($class);
            }
        }

        return null;
    }

    /**
     * Attempt to call the tool on a resolved handler instance.
     *
     * @throws \RuntimeException
     */
    private function callOnHandler(mixed $handler, string $toolName, mixed $arguments): mixed
    {
        // Strategy 1: $handler->executeTool($toolName, $arguments)
        if (method_exists($handler, 'executeTool')) {
            return $handler->executeTool($toolName, $arguments);
        }

        // Strategy 2: $handler->$toolName($arguments)
        if (method_exists($handler, $toolName)) {
            return $handler->{$toolName}($arguments);
        }

        // Strategy 3: resolve $toolName as an invokable from the container
        if ($this->app->bound($toolName)) {
            $invokable = $this->app->make($toolName);
            return $invokable($arguments);
        }

        $handlerClass = get_class($handler);
        throw new \RuntimeException(
            "ToolExecutor: handler '{$handlerClass}' does not implement executeTool(), " .
            "has no method '{$toolName}()', and '{$toolName}' is not bound in the container."
        );
    }

    /**
     * Read functions.json and check the tool is declared there.
     * Non-fatal: throws only if the file exists but the tool is absent.
     *
     * @throws \RuntimeException
     */
    private function assertToolKnown(string $toolName): void
    {
        $path = app_path('Services/functions.json');

        if (!file_exists($path)) {
            // functions.json is optional — the host app may not have one yet
            return;
        }

        $functions = json_decode(file_get_contents($path), true);

        if (!is_array($functions)) {
            return;
        }

        $names = array_map(
            fn($fn) => $fn['name'] ?? ($fn['function']['name'] ?? null),
            $functions
        );

        if (!in_array($toolName, array_filter($names), true)) {
            throw new \RuntimeException(
                "ToolExecutor: tool '{$toolName}' is not declared in functions.json."
            );
        }
    }
}
