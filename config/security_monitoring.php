<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Security Monitoring
    |--------------------------------------------------------------------------
    |
    | Base config for /admin/security. This module is designed to pull signals
    | from the application + optional providers (e.g. Cloudflare) and generate
    | incidents / actions with audit trail.
    |
    */

    'cache' => [
        'health_ttl' => (int) env('SECURITY_MONITORING_HEALTH_TTL', 10),
    ],

    'queue' => [
        // process: espera worker (supervisor/queue:work) consumindo a fila
        // cron: endpoints/commands rodam sync (ou via cron dedicado) e nao dependem de worker
        'worker_mode' => env('SECURITY_QUEUE_WORKER_MODE', env('MONITORING_QUEUE_WORKER_MODE', 'process')),
        'name' => env('SECURITY_QUEUE_NAME', 'maintenance'),
    ],

    'retention' => [
        // Default retention for security tables. Tune per environment.
        'events_days' => (int) env('SECURITY_RETENTION_EVENTS_DAYS', 90),
        'actions_days' => (int) env('SECURITY_RETENTION_ACTIONS_DAYS', 180),
        'incidents_days' => (int) env('SECURITY_RETENTION_INCIDENTS_DAYS', 365),
    ],

    'risk' => [
        'window_minutes' => (int) env('SECURITY_RISK_WINDOW_MINUTES', 15),
        'thresholds' => [
            'login_failed_per_ip' => (int) env('SECURITY_RISK_LOGIN_FAILED_PER_IP', 20),
            'forbidden_per_ip' => (int) env('SECURITY_RISK_FORBIDDEN_PER_IP', 50),
            'firewall_events_per_ip' => (int) env('SECURITY_RISK_FIREWALL_EVENTS_PER_IP', 30),
        ],
    ],

    'cloudflare' => [
        'api_token' => env('CLOUDFLARE_API_TOKEN'),
        'zone_id' => env('CLOUDFLARE_ZONE_ID'),
        'ingest_limit' => (int) env('CLOUDFLARE_INGEST_LIMIT', 250),
    ],
];
