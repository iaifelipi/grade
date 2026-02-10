<?php

return [
    'db' => [
        'latency_warning_ms' => (int) env('MONITORING_DB_LATENCY_WARNING_MS', 250),
        'latency_critical_ms' => (int) env('MONITORING_DB_LATENCY_CRITICAL_MS', 800),
    ],
    'queue' => [
        'worker_mode' => env('MONITORING_QUEUE_WORKER_MODE', 'process'),
        'backlog_warning' => (int) env('MONITORING_QUEUE_BACKLOG_WARNING', 120),
        'backlog_critical' => (int) env('MONITORING_QUEUE_BACKLOG_CRITICAL', 500),
        'fail_15m_warning' => (int) env('MONITORING_QUEUE_FAIL_15M_WARNING', 3),
        'fail_15m_critical' => (int) env('MONITORING_QUEUE_FAIL_15M_CRITICAL', 10),
    ],
];
