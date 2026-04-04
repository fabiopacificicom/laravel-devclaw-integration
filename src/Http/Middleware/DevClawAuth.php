<?php

namespace Pacificdev\DevClaw\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class DevClawAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredToken = config('devclaw.token');

        if (empty($configuredToken)) {
            return response()->json([
                'error' => 'DevClaw token not configured. Set DEVCLAW_TOKEN in your .env file.',
            ], 503);
        }

        $bearerToken = $request->bearerToken();

        if (empty($bearerToken) || !hash_equals($configuredToken, $bearerToken)) {
            return response()->json([
                'error' => 'Unauthorized. Invalid or missing Bearer token.',
            ], 401);
        }

        if (config('devclaw.log_requests', true)) {
            logger()->info('DevClaw API request', [
                'method' => $request->method(),
                'path'   => $request->path(),
                'ip'     => $request->ip(),
            ]);
        }

        return $next($request);
    }
}
