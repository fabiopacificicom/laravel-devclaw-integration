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
