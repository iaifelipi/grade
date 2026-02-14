<?php

return [
    'admin_lists' => [
        'enabled' => env('ADMIN_LISTS_PERF_ENABLED', true),
        'budgets' => [
            'index' => [
                'total_ms' => 450,
                'query_ms' => 260,
                'query_count' => 30,
                'payload_items' => 60,
            ],
            'overview' => [
                'total_ms' => 550,
                'query_ms' => 320,
                'query_count' => 40,
                'payload_items' => 60,
            ],
            'subscribers' => [
                'total_ms' => 700,
                'query_ms' => 450,
                'query_count' => 50,
                'payload_items' => 120,
            ],
        ],
    ],
];
