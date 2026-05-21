<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (!config('performance.enabled')) {
            return;
        }

        app()->instance('slow_query_profiler.query_count', 0);
        app()->instance('slow_query_profiler.query_time_ms', 0.0);

        DB::listen(function ($query) {
            $count = (int) app('slow_query_profiler.query_count', 0) + 1;
            $total_time = (float) app('slow_query_profiler.query_time_ms', 0) + (float) $query->time;

            app()->instance('slow_query_profiler.query_count', $count);
            app()->instance('slow_query_profiler.query_time_ms', $total_time);

            $threshold_ms = max(1, (int) config('performance.slow_query_ms', 200));

            if ($query->time < $threshold_ms) {
                return;
            }

            Log::info('Slow query detected', [
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'time_ms' => round((float) $query->time, 2),
            ]);
        });
    }
}
