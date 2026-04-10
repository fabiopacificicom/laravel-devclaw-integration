<?php

namespace Pacificdev\DevClaw;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * DevClawClient — PHP client for the DevClaw API
 *
 * Use this in external projects (MicroClaw, standalone scripts, etc.)
 * to communicate with a Laravel app that has this package installed.
 *
 * Usage:
 *   $client = new DevClawClient('https://fabiopacifici.com', 'your-token');
 *   $response = $client->chat('What courses are available?');
 *   $tools = $client->tools();
 *   $alive = $client->health();
 */
class DevClawClient
{
    private PendingRequest $http;

    public function __construct(
        private string $baseUrl,
        private string $token
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->http = Http::withToken($this->token)
            ->acceptJson()
            ->baseUrl($this->baseUrl . '/api/devclaw');
    }

    /**
     * Send a message to the admin AI agent.
     *
     * @param  string  $message  The message to send
     * @param  array   $context  Optional context key/value pairs
     * @param  array   $history  Optional conversation history (OpenAI format)
     * @param  string|null $model Override the AI model
     * @return array  ['response' => ..., 'model' => ...]
     * @throws \RuntimeException on HTTP error
     */
    public function chat(
        string $message,
        array $context = [],
        array $history = [],
        ?string $model = null
    ): array {
        $payload = [
            'message' => $message,
            'context' => $context,
            'history' => $history,
        ];

        if ($model !== null) {
            $payload['model'] = $model;
        }

        $response = $this->http->post('chat', $payload);

        $this->assertSuccess($response, 'chat');

        return $response->json();
    }

    /**
     * Send a message through the full agent loop.
     *
     * Uses the exact same pipeline as the Admin UI (/admin/chat):
     * - Same system prompt, context and platform snapshot
     * - Same tool definitions from functions.json
     * - Same HandleAgentFunctionCall job dispatch
     * - Persists to AdminConversation / AdminMessage
     *
     * When the AI calls a tool the job is queued and the response will include
     * `queued: true` plus a `conversation_id` you can poll with agentStatus().
     *
     * @param  string      $message         The user message
     * @param  array       $tools           Active tool names (mirrors the Admin UI tool toggles)
     * @param  int|null    $conversationId  Attach to an existing conversation, or omit to create a new one
     * @param  string|null $model           AI model override
     * @return array  ['response' => ..., 'conversation_id' => ..., 'queued' => bool, 'function_call'? => ...]
     */
    public function agent(
        string $message,
        array $tools = [],
        ?int $conversationId = null,
        ?string $model = null
    ): array {
        $payload = ['message' => $message, 'tools' => $tools];

        if ($conversationId !== null) {
            $payload['conversation_id'] = $conversationId;
        }
        if ($model !== null) {
            $payload['model'] = $model;
        }

        $response = $this->http->post('agent', $payload);
        $this->assertSuccess($response, 'agent');
        return $response->json();
    }

    /**
     * Poll the status / messages of an agent conversation.
     *
     * @param  int  $conversationId
     * @return array  ['conversation_id' => ..., 'messages' => [...]]
     */
    public function agentStatus(int $conversationId): array
    {
        $response = $this->http->get("agent/{$conversationId}/status");
        $this->assertSuccess($response, "agent/{$conversationId}/status");
        return $response->json();
    }

    /**
     * List all available agent tools.
     *
     * @return array  ['tools' => [...]]
     */
    public function tools(): array
    {
        $response = $this->http->get('tools');
        $this->assertSuccess($response, 'tools');
        return $response->json();
    }

    /**
     * Execute a specific tool by name.
     *
     * @param  string  $toolName   The tool name (e.g. 'get_courses')
     * @param  array   $arguments  Tool arguments
     * @return array  ['tool' => ..., 'result' => ...]
     */
    public function executeTool(string $toolName, array $arguments = []): array
    {
        $response = $this->http->post("tools/{$toolName}", $arguments);
        $this->assertSuccess($response, "tools/{$toolName}");
        return $response->json();
    }

    /**
     * List courses from the platform.
     *
     * @return array  ['courses' => [...]]
     */
    public function courses(): array
    {
        $response = $this->http->get('courses');
        $this->assertSuccess($response, 'courses');
        return $response->json();
    }

    /**
     * Get platform statistics.
     *
     * @return array  ['stats' => [...]]
     */
    public function stats(): array
    {
        $response = $this->http->get('stats');
        $this->assertSuccess($response, 'stats');
        return $response->json();
    }

    /**
     * Check if the API is reachable and healthy.
     *
     * @return bool  true if status is 'ok'
     */
    public function health(): bool
    {
        try {
            $response = $this->http->get('health');
            if (!$response->successful()) {
                return false;
            }
            return ($response->json('status') === 'ok');
        } catch (\Throwable) {
            return false;
        }
    }

    // -----------------------------------------------------------------------

    private function assertSuccess(
        \Illuminate\Http\Client\Response $response,
        string $endpoint
    ): void {
        if (!$response->successful()) {
            $error = $response->json('error') ?? $response->body();
            throw new \RuntimeException(
                "DevClaw API error on [{$endpoint}] HTTP {$response->status()}: {$error}"
            );
        }
    }
}
