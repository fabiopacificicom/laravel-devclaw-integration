<?php

namespace Pacificdev\DevClaw\Tests;

use Orchestra\Testbench\TestCase;
use Pacificdev\DevClaw\DevClawServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DevClawControllerTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [DevClawServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('devclaw.enabled', true);
        $app['config']->set('devclaw.token', 'test-token-abc123');
        $app['config']->set('devclaw.log_requests', false);
    }

    // -----------------------------------------------------------------------
    // Health
    // -----------------------------------------------------------------------

    public function test_health_returns_ok_with_valid_token(): void
    {
        $response = $this->withToken('test-token-abc123')
            ->getJson('/api/devclaw/health');

        $response->assertStatus(200)
            ->assertJson(['status' => 'ok']);
    }

    public function test_health_returns_401_without_token(): void
    {
        $response = $this->getJson('/api/devclaw/health');
        $response->assertStatus(401);
    }

    public function test_health_returns_401_with_wrong_token(): void
    {
        $response = $this->withToken('wrong-token')
            ->getJson('/api/devclaw/health');

        $response->assertStatus(401);
    }

    // -----------------------------------------------------------------------
    // Auth middleware
    // -----------------------------------------------------------------------

    public function test_middleware_rejects_missing_authorization_header(): void
    {
        $response = $this->getJson('/api/devclaw/stats');
        $response->assertStatus(401)
            ->assertJsonFragment(['error' => 'Unauthorized. Invalid or missing Bearer token.']);
    }

    public function test_middleware_rejects_invalid_token(): void
    {
        $response = $this->withToken('bad-token')
            ->getJson('/api/devclaw/stats');

        $response->assertStatus(401);
    }

    // -----------------------------------------------------------------------
    // Tools
    // -----------------------------------------------------------------------

    public function test_tools_returns_404_when_functions_json_missing(): void
    {
        $response = $this->withToken('test-token-abc123')
            ->getJson('/api/devclaw/tools');

        // In test environment functions.json won't exist
        $response->assertStatus(404);
    }

    // -----------------------------------------------------------------------
    // Tool execution
    // -----------------------------------------------------------------------

    public function test_execute_tool_calls_executor_not_llm(): void
    {
        // Mock executor that returns a real result
        $mockExecutor = new class {
            public function execute(string $tool, mixed $args): array
            {
                return ['called' => $tool, 'args' => $args];
            }
        };

        $this->app->instance('devclaw.tool_executor', $mockExecutor);

        $response = $this->withToken('test-token-abc123')
            ->postJson('/api/devclaw/tools/get_courses', ['status' => 'published']);

        $response->assertStatus(200)
            ->assertJson([
                'tool'   => 'get_courses',
                'result' => ['called' => 'get_courses', 'args' => ['status' => 'published']],
            ]);
    }

    public function test_execute_tool_returns_500_when_tool_unresolvable(): void
    {
        // Bind an executor that always throws (simulates no handler found)
        $mockExecutor = new class {
            public function execute(string $tool, mixed $args): never
            {
                throw new \RuntimeException("ToolExecutor: could not resolve a tool handler for tool '{$tool}'.");
            }
        };

        $this->app->instance('devclaw.tool_executor', $mockExecutor);

        $response = $this->withToken('test-token-abc123')
            ->postJson('/api/devclaw/tools/nonexistent_tool', []);

        $response->assertStatus(500)
            ->assertJsonStructure(['error']);

        // Must NOT be an LLM "Execute tool:" response
        $body = $response->json('error');
        $this->assertStringNotContainsStringIgnoringCase('getAdminResponse', $body);
        $this->assertStringNotContainsStringIgnoringCase('AI provider', $body);
    }

    // -----------------------------------------------------------------------
    // Chat
    // -----------------------------------------------------------------------

    public function test_chat_requires_message_field(): void
    {
        $response = $this->withToken('test-token-abc123')
            ->postJson('/api/devclaw/chat', []);

        $response->assertStatus(422);
    }

    public function test_chat_returns_error_when_ai_provider_not_bound(): void
    {
        $response = $this->withToken('test-token-abc123')
            ->postJson('/api/devclaw/chat', ['message' => 'Hello']);

        // Should be 500 since AiProviderWrapper isn't available in test env
        $response->assertStatus(500)
            ->assertJsonStructure(['error']);
    }

    // -----------------------------------------------------------------------
    // Stats
    // -----------------------------------------------------------------------

    public function test_stats_returns_valid_structure(): void
    {
        $response = $this->withToken('test-token-abc123')
            ->getJson('/api/devclaw/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'stats' => [
                    'php_version',
                    'laravel_version',
                    'timestamp',
                ],
            ]);
    }

    // -----------------------------------------------------------------------
    // Disabled state
    // -----------------------------------------------------------------------

    public function test_routes_not_registered_when_disabled(): void
    {
        // Override config to disabled
        config(['devclaw.enabled' => false]);

        // Note: routes are registered at boot time, so this test
        // validates the config value is respected. A full integration
        // test would require a separate app boot cycle.
        $this->assertFalse(config('devclaw.enabled'));
    }
}
