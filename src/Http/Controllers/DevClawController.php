<?php

namespace Pacificdev\DevClaw\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class DevClawController extends Controller
{
    /**
     * GET /api/devclaw/health
     * Health check endpoint.
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status'  => 'ok',
            'package' => 'pacificdev/laravel-devclaw-integration',
            'version' => '0.1.0',
            'enabled' => config('devclaw.enabled'),
        ]);
    }

    /**
     * POST /api/devclaw/chat
     * Send a message to the platform admin AI agent.
     *
     * Body:
     *   message  (string, required)
     *   context  (array,  optional)
     *   history  (array,  optional)
     *   model    (string, optional)
     */
    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:1000000',
            'context' => 'sometimes|array',
            'history' => 'sometimes|array',
            'model'   => 'sometimes|nullable|string',
        ]);

        $message = $request->input('message');
        $context = $request->input('context', []);
        $history = $request->input('history', []);
        $model   = $request->input('model');

        try {
            // Resolve AiProviderWrapper (or any binding the host app has set up)
            // We try multiple resolution strategies for flexibility.
            $ai = $this->resolveAiProvider();

            $response = $ai->getAdminResponse($message, $context, $history, $model);

            // Optionally persist to AdminConversation if the model exists
            $this->maybePersistMessage($message, $response);

            return response()->json([
                'response' => $response,
                'model'    => $model ?? config('devclaw.default_model'),
            ]);
        } catch (\Throwable $e) {
            Log::error('DevClaw chat error', ['error' => $e->getMessage()]);
            return response()->json([
                'error'   => 'AI provider error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/devclaw/tools
     * List all available agent tools (from functions.json).
     */
    public function tools(): JsonResponse
    {
        $path = app_path('Services/functions.json');

        if (!file_exists($path)) {
            return response()->json([
                'error' => 'functions.json not found at ' . $path,
            ], 404);
        }

        $functions = json_decode(file_get_contents($path), true);

        // Unwrap {"functions": [...]} wrapper if present
        if (isset($functions['functions']) && is_array($functions['functions'])) {
            $functions = $functions['functions'];
        }

        // Filter by allowed_tools if configured
        $allowed = config('devclaw.allowed_tools', '*');
        if ($allowed !== '*') {
            $allowedList = array_map('trim', explode(',', $allowed));
            $functions = array_filter($functions, function ($fn) use ($allowedList) {
                $name = $fn['name'] ?? ($fn['function']['name'] ?? null);
                return in_array($name, $allowedList, true);
            });
            $functions = array_values($functions);
        }

        return response()->json(['tools' => $functions]);
    }

    /**
     * POST /api/devclaw/tools/{tool}
     * Execute a specific tool directly by name.
     *
     * Body: tool arguments as JSON object
     */
    public function executeTool(Request $request, string $tool): JsonResponse
    {
        // Validate tool is allowed
        $allowed = config('devclaw.allowed_tools', '*');
        if ($allowed !== '*') {
            $allowedList = array_map('trim', explode(',', $allowed));
            if (!in_array($tool, $allowedList, true)) {
                return response()->json(['error' => "Tool '{$tool}' is not in the allowed list."], 403);
            }
        }

        $arguments = $request->all();

        try {
            $executor = app('devclaw.tool_executor');
            $result = $executor->execute($tool, $arguments);
            return response()->json(['tool' => $tool, 'result' => $result]);
        } catch (\Throwable $e) {
            Log::error("DevClaw tool execution error [{$tool}]", ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/devclaw/agent
     * Full agent loop — delegates to the platform's AdminAgentController when available,
     * which uses the exact same pipeline as the Admin UI (context building, tool list,
     * HandleAgentFunctionCall dispatch, etc.).
     *
     * Body:
     *   message          (string, required)  — user message
     *   conversation_id  (int, optional)     — attach to existing conversation (omit to create new)
     *   tools            (array, optional)   — active tool names (same as admin UI activeTools)
     *   model            (string, optional)  — AI model override
     */
    public function agent(Request $request): JsonResponse
    {
        // Delegate to the platform's AdminAgentController so agent-to-agent communication
        // uses the identical pipeline as the Admin UI — same context, same tool dispatch,
        // same HandleAgentFunctionCall job. No duplication.
        $platformController = 'App\\Http\\Controllers\\Api\\AdminAgentController';
        if (app()->bound($platformController) || class_exists($platformController)) {
            return app($platformController)->sendMessage($request);
        }

        // Fallback when the platform controller is not present (standalone / testing)
        $request->validate([
            'message'         => 'required|string|max:1000000',
            'conversation_id' => 'sometimes|nullable|integer',
            'model'           => 'sometimes|nullable|string',
        ]);

        try {
            $ai = $this->resolveAiProvider();

            $convModel = $this->resolveModel(['App\\Models\\AdminConversation']);
            $msgModel  = $this->resolveModel(['App\\Models\\AdminMessage']);

            if (!$convModel || !$msgModel) {
                $response = $ai->getAdminResponse($request->input('message'), [], [], $request->input('model'));
                return response()->json(['response' => $response, 'conversation_id' => null]);
            }

            $convId = $request->input('conversation_id');
            $conversation = $convId ? $convModel::find($convId) : null;
            if (!$conversation) {
                $conversation = $convModel::create([
                    'source' => 'devclaw',
                    'title'  => 'DevClaw Agent — ' . now()->format('Y-m-d H:i'),
                ]);
            }

            $msgModel::create([
                'admin_conversation_id' => $conversation->id,
                'content' => $request->input('message'),
                'is_ai'   => false,
            ]);

            $history = $conversation->messages()
                ->orderBy('created_at', 'desc')
                ->take(20)
                ->get()
                ->reverse()
                ->map(fn($m) => ['role' => ($m->is_ai ?? false) ? 'assistant' : 'user', 'content' => $m->content])
                ->values()
                ->all();

            $response = $ai->getAdminResponse($request->input('message'), [], $history, $request->input('model'));

            $decoded = json_decode($response, true, 512, JSON_OBJECT_AS_ARRAY);
            $isFunctionCall = json_last_error() === JSON_ERROR_NONE
                && (isset($decoded['function_call']) || isset($decoded['name']));

            if ($isFunctionCall) {
                $payload  = $decoded['function_call'] ?? $decoded;
                $jobClass = 'App\\Jobs\\HandleAgentFunctionCall';
                $statusMsg = class_exists($jobClass)
                    ? (fn() => $jobClass::dispatch($conversation->id, $payload, $request->input('model'), []) ?: 'Tool execution queued — check the conversation for results.')()
                    : 'Function call detected but HandleAgentFunctionCall job not found.';

                $msgModel::create(['admin_conversation_id' => $conversation->id, 'content' => $statusMsg, 'is_ai' => true]);

                return response()->json([
                    'response'        => $statusMsg,
                    'function_call'   => $payload,
                    'conversation_id' => $conversation->id,
                    'queued'          => true,
                ]);
            }

            $msgModel::create(['admin_conversation_id' => $conversation->id, 'content' => $response, 'is_ai' => true]);

            return response()->json([
                'response'        => $response,
                'conversation_id' => $conversation->id,
                'queued'          => false,
            ]);

        } catch (\Throwable $e) {
            Log::error('DevClaw agent fallback error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Agent error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/devclaw/agent/{conversation_id}/status
     * Poll the result of a queued agent tool call.
     * Delegates to the platform's AdminAgentController when available.
     */
    public function agentStatus(Request $request, int $conversationId): JsonResponse
    {
        $platformController = 'App\\Http\\Controllers\\Api\\AdminAgentController';
        if (app()->bound($platformController) || class_exists($platformController)) {
            return app($platformController)->status($conversationId);
        }

        // Fallback
        $convModel = $this->resolveModel(['App\\Models\\AdminConversation']);
        $msgModel  = $this->resolveModel(['App\\Models\\AdminMessage']);

        if (!$convModel || !$msgModel) {
            return response()->json(['error' => 'AdminConversation model not found.'], 404);
        }

        $conversation = $convModel::find($conversationId);
        if (!$conversation) {
            return response()->json(['error' => 'Conversation not found.'], 404);
        }

        $messages = $msgModel::where('admin_conversation_id', $conversationId)
            ->orderBy('created_at', 'asc')
            ->get(['role', 'content', 'is_ai', 'created_at'])
            ->map(fn($m) => [
                'role'    => ($m->is_ai ?? false) ? 'assistant' : 'user',
                'content' => $m->content,
                'time'    => $m->created_at,
            ]);

        return response()->json([
            'conversation_id' => $conversationId,
            'messages'        => $messages,
        ]);
    }

    /**
     * GET /api/devclaw/courses
     * List courses (read-only). Requires the host app to have a Course model.
     */
    public function courses(Request $request): JsonResponse
    {
        try {
            // Try common course model namespaces
            $courseModel = $this->resolveModel(['App\\Models\\Course', 'App\\Course']);

            if (!$courseModel) {
                return response()->json(['error' => 'Course model not found in this application.'], 404);
            }

            $query = $courseModel::select(['id', 'title', 'slug', 'status', 'is_active', 'created_at'])
                ->orderBy('created_at', 'desc');

            // Optional filter: ?public_only=true returns only publicly visible courses
            if ($request->boolean('public_only')) {
                $query->where('is_active', true);
            }

            $courses = $query->get();

            return response()->json(['courses' => $courses]);
        } catch (\Throwable $e) {
            Log::error('DevClaw courses error', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/devclaw/stats
     * Return basic platform statistics.
     */
    public function stats(): JsonResponse
    {
        $stats = [];

        $modelMap = [
            'users'         => ['App\\Models\\User'],
            'courses'       => ['App\\Models\\Course', 'App\\Course'],
            'conversations' => ['App\\Models\\AdminConversation'],
            'messages'      => ['App\\Models\\AdminMessage'],
        ];

        foreach ($modelMap as $key => $candidates) {
            $model = $this->resolveModel($candidates);
            if ($model) {
                try {
                    $stats[$key] = $model::count();
                } catch (\Throwable $e) {
                    $stats[$key] = null;
                }
            } else {
                $stats[$key] = null;
            }
        }

        $stats['php_version']     = PHP_VERSION;
        $stats['laravel_version'] = app()->version();
        $stats['timestamp']       = now()->toIso8601String();

        return response()->json(['stats' => $stats]);
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function resolveAiProvider(): mixed
    {
        // Try common bindings/class names from the host app
        $candidates = [
            'AiProviderWrapper',
            'App\\Services\\AiProviderWrapper',
            'App\\Services\\AI\\AiProviderWrapper',
        ];

        foreach ($candidates as $class) {
            if (app()->bound($class)) {
                return app($class);
            }
            if (class_exists($class)) {
                return app($class);
            }
        }

        // Try interface binding
        $interfaces = [
            'App\\Contracts\\AiProviderInterface',
            'App\\Services\\AiProviderInterface',
        ];
        foreach ($interfaces as $iface) {
            if (app()->bound($iface)) {
                return app($iface);
            }
        }

        throw new \RuntimeException(
            'Could not resolve AiProviderWrapper. ' .
            'Bind it in your AppServiceProvider or ensure the class is autoloaded.'
        );
    }

    private function resolveModel(array $candidates): ?string
    {
        foreach ($candidates as $class) {
            if (class_exists($class)) {
                return $class;
            }
        }
        return null;
    }

    private function maybePersistMessage(string $message, mixed $response): void
    {
        try {
            $convModel = $this->resolveModel(['App\\Models\\AdminConversation']);
            $msgModel  = $this->resolveModel(['App\\Models\\AdminMessage']);

            if (!$convModel || !$msgModel) {
                return;
            }

            $conv = $convModel::firstOrCreate(
                ['source' => 'devclaw'],
                ['title' => 'DevClaw Agent Conversation']
            );

            $msgModel::create([
                'admin_conversation_id' => $conv->id,
                'role'    => 'user',
                'content' => $message,
            ]);

            $msgModel::create([
                'admin_conversation_id' => $conv->id,
                'role'    => 'assistant',
                'content' => is_string($response) ? $response : json_encode($response),
            ]);
        } catch (\Throwable) {
            // Persistence is optional; silently fail
        }
    }
}
