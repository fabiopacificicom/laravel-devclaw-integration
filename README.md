# laravel-devclaw-integration

**DevClaw agent integration for Laravel** — exposes a secure REST API so Olly/OpenClaw can communicate directly with your platform's admin AI agent.

[![PHP](https://img.shields.io/badge/PHP-%5E8.2-blue)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-11%20%7C%2012-red)](https://laravel.com)

---

## What this package does

Installs a set of authenticated API routes under `/api/devclaw/*` that allow an external agent (Olly, OpenClaw, MicroClaw, or any HTTP client) to:

- **Send messages** to the platform's admin AI (`AiProviderWrapper::getAdminResponse`)
- **List agent tools** defined in `app/Services/functions.json`
- **Execute tools** directly by name
- **Read course data** and **platform stats**
- Optionally **persist conversations** to `AdminConversation` / `AdminMessage`

All routes are protected by a Bearer token that you control.

---

## Requirements

- PHP ^8.2
- Laravel 11 or 12
- `AiProviderWrapper` (or similar) accessible in the host app

---

## Installation

### 1. Require the package

```bash
# If published to Packagist:
composer require pacificdev/laravel-devclaw-integration

# Or, for local development, add to composer.json repositories:
{
    "repositories": [
        {
            "type": "path",
            "url": "../laravel-devclaw-integration"
        }
    ]
}
composer require pacificdev/laravel-devclaw-integration
```

### 2. Publish config (optional)

```bash
php artisan vendor:publish --tag=devclaw-config
```

### 3. Add environment variables

```dotenv
DEVCLAW_ENABLED=true
DEVCLAW_TOKEN=<your-generated-token>
DEVCLAW_DEFAULT_MODEL=
DEVCLAW_ALLOWED_TOOLS=*
DEVCLAW_LOG_REQUESTS=true
```

### 4. Generate a token

```bash
openssl rand -hex 32
```

---

## Available Endpoints

All routes require `Authorization: Bearer <token>` header.

### `GET /api/devclaw/health`

Health check.

```bash
curl -H "Authorization: Bearer $TOKEN" https://yoursite.com/api/devclaw/health
```

```json
{
  "status": "ok",
  "package": "pacificdev/laravel-devclaw-integration",
  "version": "0.1.0",
  "enabled": true
}
```

---

### `POST /api/devclaw/chat`

Send a message to the admin AI agent.

**Request:**
```json
{
  "message": "What courses are currently published?",
  "context": { "source": "olly" },
  "history": [],
  "model": null
}
```

**Response:**
```json
{
  "response": "There are 12 published courses...",
  "model": null
}
```

**Function call response** (when the AI invokes a tool):
```json
{
  "response": {
    "function_call": {
      "name": "get_courses",
      "arguments": "{\"status\": \"published\"}"
    }
  },
  "model": null
}
```

---

### `GET /api/devclaw/tools`

List all available agent tools from `app/Services/functions.json`.

```json
{
  "tools": [
    {
      "name": "get_courses",
      "description": "Retrieve a list of courses",
      "parameters": { ... }
    }
  ]
}
```

---

### `POST /api/devclaw/tools/{tool}`

Execute a specific tool directly.

```bash
curl -X POST \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"status": "published"}' \
  https://yoursite.com/api/devclaw/tools/get_courses
```

```json
{
  "tool": "get_courses",
  "result": [ ... ]
}
```

---

### `GET /api/devclaw/courses`

List courses (read-only, requires `App\Models\Course`).

```json
{
  "courses": [
    { "id": 1, "title": "Laravel for Beginners", "slug": "laravel-beginners", "status": "published" }
  ]
}
```

---

### `GET /api/devclaw/stats`

Platform statistics.

```json
{
  "stats": {
    "users": 142,
    "courses": 24,
    "conversations": 87,
    "messages": 1203,
    "php_version": "8.3.0",
    "laravel_version": "12.0.0",
    "timestamp": "2026-04-04T16:00:00+02:00"
  }
}
```

---

## Tool Execution

When `POST /api/devclaw/tools/{tool}` is called, the package resolves and calls the actual tool implementation in your host app — it never falls back to asking the LLM to "execute" it verbally.

### How the package finds your tool handler

Resolution happens in this order:

1. **`devclaw.platform_tools` container binding** — if your `AppServiceProvider` binds this key, that instance is used first.
2. **`App\Services\AgentToolHandler`** — if this class exists it is resolved via the container.
3. **`App\Services\ToolHandler`** — same.
4. **`App\Http\Controllers\Admin\AgentController`** — same.

Once a handler is found, the executor tries to call the tool in this order:

1. `$handler->executeTool($toolName, $arguments)` — universal dispatcher method.
2. `$handler->{$toolName}($arguments)` — method named after the tool.
3. The container tries to resolve `$toolName` as an invokable class.

If none of the above succeeds, a `\RuntimeException` is thrown and the endpoint returns HTTP 500 with a clear error message.

### Manually binding a custom executor

You can bind your own implementation of the executor in `AppServiceProvider`:

```php
// app/Providers/AppServiceProvider.php

use Pacificdev\DevClaw\Services\ToolExecutor;

public function register(): void
{
    // Bind a custom platform_tools handler
    $this->app->singleton('devclaw.platform_tools', fn () => new MyCustomToolHandler());

    // — OR — replace the ToolExecutor entirely
    $this->app->singleton('devclaw.tool_executor', function ($app) {
        return new class($app) extends ToolExecutor {
            public function execute(string $toolName, mixed $arguments): mixed
            {
                // your custom dispatch logic
            }
        };
    });
}
```

### Using the auto-discovery (recommended)

Simply create `App\Services\AgentToolHandler` with an `executeTool` method:

```php
namespace App\Services;

class AgentToolHandler
{
    public function executeTool(string $tool, mixed $arguments): mixed
    {
        return match ($tool) {
            'get_courses' => $this->getCourses($arguments),
            default       => throw new \RuntimeException("Unknown tool: {$tool}"),
        };
    }
}
```

No extra binding needed — the package finds it automatically.

---



The package ships a `DevClawClient` PHP class you can use in external projects:

```php
use Pacificdev\DevClaw\DevClawClient;

$client = new DevClawClient('https://fabiopacifici.com', 'your-token');

// Health check
$alive = $client->health(); // bool

// Chat
$result = $client->chat('List all published courses', ['source' => 'olly']);
echo $result['response'];

// Tools
$tools = $client->tools()['tools'];

// Execute a tool
$data = $client->executeTool('get_courses', ['status' => 'published']);

// Stats
$stats = $client->stats()['stats'];
```

---

## Security Notes

1. **Rotate your token regularly** — treat it like a password.
2. **Use HTTPS** — never send Bearer tokens over plain HTTP in production.
3. **`DEVCLAW_ALLOWED_TOOLS`** — restrict to specific tool names if you don't need all 24:
   ```dotenv
   DEVCLAW_ALLOWED_TOOLS=get_courses,get_stats,search_content
   ```
4. **Disable when not in use** — set `DEVCLAW_ENABLED=false` in production if Olly isn't actively connected.
5. **Firewall** — consider IP allowlisting at the server level if the agent always connects from a known IP.
6. **`DEVCLAW_LOG_REQUESTS=true`** — keep enabled so you have an audit trail.

---

## License

MIT
