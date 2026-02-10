<?php

return [
    'run' => [
        'job_tries' => (int) env('AUTOMATION_RUN_JOB_TRIES', 3),
        'job_backoff_seconds' => env('AUTOMATION_RUN_JOB_BACKOFF_SECONDS', '10,30,90'),
        'lock_seconds' => (int) env('AUTOMATION_RUN_LOCK_SECONDS', 3600),
    ],

    'email' => [
        'default_subject' => env('AUTOMATION_EMAIL_SUBJECT', 'Mensagem Pixip'),
        'default_message' => env('AUTOMATION_EMAIL_MESSAGE', 'Olá {nome}, esta é uma mensagem automática da Pixip.'),
    ],

    'sms' => [
        'webhook_url' => env('AUTOMATION_SMS_WEBHOOK_URL', ''),
        'timeout_seconds' => env('AUTOMATION_SMS_TIMEOUT', 10),
        'default_message' => env('AUTOMATION_SMS_MESSAGE', 'Olá {nome}, mensagem automática da Pixip.'),
        'retry_attempts' => (int) env('AUTOMATION_SMS_RETRY_ATTEMPTS', 3),
        'retry_backoff_ms' => env('AUTOMATION_SMS_RETRY_BACKOFF_MS', '500,1500,3500'),
        'retryable_statuses' => env('AUTOMATION_SMS_RETRYABLE_STATUSES', '408,409,425,429,500,502,503,504'),
        'retry_on_exceptions' => env('AUTOMATION_SMS_RETRY_ON_EXCEPTIONS', true),
        'retryable_exception_patterns' => env(
            'AUTOMATION_SMS_RETRYABLE_EXCEPTION_PATTERNS',
            'timeout,timed out,connection refused,connection reset,temporarily unavailable,server has gone away'
        ),
    ],

    'whatsapp' => [
        'webhook_url' => env('AUTOMATION_WHATSAPP_WEBHOOK_URL', ''),
        'timeout_seconds' => env('AUTOMATION_WHATSAPP_TIMEOUT', 10),
        'default_message' => env('AUTOMATION_WHATSAPP_MESSAGE', 'Olá {nome}, mensagem automática da Pixip.'),
        'retry_attempts' => (int) env('AUTOMATION_WHATSAPP_RETRY_ATTEMPTS', 3),
        'retry_backoff_ms' => env('AUTOMATION_WHATSAPP_RETRY_BACKOFF_MS', '500,1500,3500'),
        'retryable_statuses' => env('AUTOMATION_WHATSAPP_RETRYABLE_STATUSES', '408,409,425,429,500,502,503,504'),
        'retry_on_exceptions' => env('AUTOMATION_WHATSAPP_RETRY_ON_EXCEPTIONS', true),
        'retryable_exception_patterns' => env(
            'AUTOMATION_WHATSAPP_RETRYABLE_EXCEPTION_PATTERNS',
            'timeout,timed out,connection refused,connection reset,temporarily unavailable,server has gone away'
        ),
    ],
];
