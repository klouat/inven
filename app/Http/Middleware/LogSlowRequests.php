<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogSlowRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);
        $response = $next($request);

        if (!config('performance.enabled')) {
            return $response;
        }

        $duration_ms = (microtime(true) - $start) * 1000;
        $threshold_ms = max(1, (int) config('performance.slow_request_ms', 500));

        if ($duration_ms >= $threshold_ms) {
            Log::info('Slow request detected', [
                'method' => $request->getMethod(),
                'path' => $request->path(),
                'route' => optional($request->route())->getName(),
                'status' => $response->getStatusCode(),
                'duration_ms' => round($duration_ms, 2),
                'query_count' => app('slow_query_profiler.query_count', 0),
                'query_time_ms' => round((float) app('slow_query_profiler.query_time_ms', 0), 2),
                'memory_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
            ]);
        }

        return $response;
    }
}
