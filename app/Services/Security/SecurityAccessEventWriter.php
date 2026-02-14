<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SecurityAccessEventWriter
{
    /**
     * @param array<string,mixed> $payload
     */
    public function write(array $payload): void
    {
        if (!Schema::hasTable('security_access_events')) {
            return;
        }

        // Never access $payload[...] directly without a default; missing keys can
        // surface as warnings in production and break the login UX.
        $ipAddress = $payload['ip_address'] ?? null;
        $requestPath = $payload['request_path'] ?? null;
        $requestMethod = $payload['request_method'] ?? null;
        $httpStatus = $payload['http_status'] ?? null;
        $userId = $payload['user_id'] ?? null;
        $userEmail = $payload['user_email'] ?? null;
        $country = $payload['country'] ?? null;
        $asn = $payload['asn'] ?? null;
        $userAgent = $payload['user_agent'] ?? null;

        $row = [
            'source' => (string) ($payload['source'] ?? 'app'),
            'event_type' => (string) ($payload['event_type'] ?? 'unknown'),
            'ip_address' => $ipAddress !== null ? (string) $ipAddress : null,
            'request_path' => $requestPath !== null ? (string) $requestPath : null,
            'request_method' => $requestMethod !== null ? (string) $requestMethod : null,
            'http_status' => $httpStatus !== null ? (int) $httpStatus : null,
            'user_id' => $userId !== null ? (int) $userId : null,
            'user_email' => $userEmail !== null ? (string) $userEmail : null,
            'country' => $country !== null ? (string) $country : null,
            'asn' => $asn !== null ? (string) $asn : null,
            'user_agent' => $userAgent !== null ? (string) $userAgent : null,
            'payload_json' => isset($payload['payload_json']) ? json_encode($payload['payload_json'], JSON_UNESCAPED_UNICODE) : null,
            'occurred_at' => $payload['occurred_at'] ?? now(),
        ];

        DB::table('security_access_events')->insert($row);
    }
}
