<?php

// -----------------------------------------------------------------------
// Health
// -----------------------------------------------------------------------

it('returns ok with valid token', function () {
    $this->withToken('test-token-abc123')
        ->getJson('/api/devclaw/health')
        ->assertOk()
        ->assertJson(['status' => 'ok']);
});

it('returns 401 without token on health', function () {
    $this->getJson('/api/devclaw/health')
        ->assertUnauthorized();
});

it('returns 401 with wrong token on health', function () {
    $this->withToken('wrong-token')
        ->getJson('/api/devclaw/health')
        ->assertUnauthorized();
});

// -----------------------------------------------------------------------
// Auth middleware
// -----------------------------------------------------------------------

it('rejects missing authorization header', function () {
    $this->getJson('/api/devclaw/stats')
        ->assertUnauthorized()
        ->assertJsonFragment(['error' => 'Unauthorized. Invalid or missing Bearer token.']);
});

it('rejects invalid token', function () {
    $this->withToken('bad-token')
        ->getJson('/api/devclaw/stats')
        ->assertUnauthorized();
});

// -----------------------------------------------------------------------
// Tools
// -----------------------------------------------------------------------

it('returns 404 when functions.json is missing', function () {
    $this->withToken('test-token-abc123')
        ->getJson('/api/devclaw/tools')
        ->assertNotFound();
});

// -----------------------------------------------------------------------
// Tool execution
// -----------------------------------------------------------------------

it('calls executor directly — not the LLM', function () {
    $mockExecutor = new class {
        public function execute(string $tool, mixed $args): array
        {
            return ['called' => $tool, 'args' => $args];
        }
    };

    app()->instance('devclaw.tool_executor', $mockExecutor);

    $this->withToken('test-token-abc123')
        ->postJson('/api/devclaw/tools/get_courses', ['status' => 'published'])
        ->assertOk()
        ->assertJson([
            'tool'   => 'get_courses',
            'result' => ['called' => 'get_courses', 'args' => ['status' => 'published']],
        ]);
});

it('returns 500 when tool handler is not resolvable', function () {
    $mockExecutor = new class {
        public function execute(string $tool, mixed $args): never
        {
            throw new \RuntimeException("ToolExecutor: could not resolve a tool handler for tool '{$tool}'.");
        }
    };

    app()->instance('devclaw.tool_executor', $mockExecutor);

    $response = $this->withToken('test-token-abc123')
        ->postJson('/api/devclaw/tools/nonexistent_tool', [])
        ->assertStatus(500)
        ->assertJsonStructure(['error']);

    // Must NOT be an LLM "AI provider" error — the executor is called directly
    $error = $response->json('error');
    expect($error)
        ->not->toContain('getAdminResponse');
    expect(strtolower($error))
        ->not->toContain('ai provider');
});

// -----------------------------------------------------------------------
// Chat
// -----------------------------------------------------------------------

it('rejects chat request without message field', function () {
    $this->withToken('test-token-abc123')
        ->postJson('/api/devclaw/chat', [])
        ->assertUnprocessable();
});

it('returns 500 for chat when AI provider is not bound', function () {
    $this->withToken('test-token-abc123')
        ->postJson('/api/devclaw/chat', ['message' => 'Hello'])
        ->assertStatus(500)
        ->assertJsonStructure(['error']);
});

// -----------------------------------------------------------------------
// Agent — delegates to AdminAgentController when available
// -----------------------------------------------------------------------

it('rejects agent request without message field', function () {
    $this->withToken('test-token-abc123')
        ->postJson('/api/devclaw/agent', [])
        ->assertUnprocessable();
});

it('uses AdminAgentController pipeline when available', function () {
    // Bind a fake AdminAgentController into the container
    $fake = new class {
        public function sendMessage(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
        {
            return response()->json([
                'response'        => 'delegated',
                'conversation_id' => 99,
                'queued'          => false,
            ]);
        }
    };

    app()->bind('App\\Http\\Controllers\\Api\\AdminAgentController', fn () => $fake);

    $this->withToken('test-token-abc123')
        ->postJson('/api/devclaw/agent', ['message' => 'Hello agent'])
        ->assertOk()
        ->assertJson(['response' => 'delegated', 'conversation_id' => 99]);
});

it('falls back to inline handler when AdminAgentController is absent', function () {
    // Ensure the platform controller class does NOT exist in this test environment
    // (it won't exist in a bare Orchestra Testbench app)
    expect(class_exists('App\\Http\\Controllers\\Api\\AdminAgentController'))->toBeFalse();

    // With no AI provider bound, the fallback should return 500 (not crash differently)
    $this->withToken('test-token-abc123')
        ->postJson('/api/devclaw/agent', ['message' => 'Hello'])
        ->assertStatus(500)
        ->assertJsonStructure(['error']);
});

// -----------------------------------------------------------------------
// Stats
// -----------------------------------------------------------------------

it('returns valid stats structure', function () {
    $this->withToken('test-token-abc123')
        ->getJson('/api/devclaw/stats')
        ->assertOk()
        ->assertJsonStructure([
            'stats' => [
                'php_version',
                'laravel_version',
                'timestamp',
            ],
        ]);
});

// -----------------------------------------------------------------------
// Disabled state
// -----------------------------------------------------------------------

it('config disabled flag can be read', function () {
    config(['devclaw.enabled' => false]);

    expect(config('devclaw.enabled'))->toBeFalse();
});
