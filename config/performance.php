<?php

return [
    'enabled' => (bool) env('PERFORMANCE_LOG_ENABLED', false),
    'slow_request_ms' => (int) env('SLOW_REQUEST_MS', 500),
    'slow_query_ms' => (int) env('SLOW_QUERY_MS', 200),
];
