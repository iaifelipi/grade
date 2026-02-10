<?php

namespace Tests\Unit;

use App\Jobs\ExecuteAutomationRunJob;
use App\Services\LeadsVault\AutomationChannelDispatchService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AutomationDispatchRetryTest extends TestCase
{
    public function test_job_backoff_reads_configured_values(): void
    {
        config()->set('automation_dispatch.run.job_tries', 4);
        config()->set('automation_dispatch.run.job_backoff_seconds', '5,15,45');

        $job = new ExecuteAutomationRunJob(1, 'tenant-test');

        $this->assertSame(4, $job->tries);
        $this->assertSame([5, 15, 45], $job->backoff());
    }

    public function test_sms_dispatch_retries_on_retryable_http_status_and_succeeds(): void
    {
        config()->set('automation_dispatch.sms.webhook_url', 'https://example.test/sms');
        config()->set('automation_dispatch.sms.timeout_seconds', 2);
        config()->set('automation_dispatch.sms.retry_attempts', 3);
        config()->set('automation_dispatch.sms.retry_backoff_ms', '1,1,1');
        config()->set('automation_dispatch.sms.retryable_statuses', '500,502');

        Http::fakeSequence()
            ->push(['ok' => false], 500)
            ->push(['id' => 'provider-123'], 200);

        $service = new AutomationChannelDispatchService();
        $lead = (object) [
            'id' => 42,
            'lead_source_id' => 10,
            'phone_e164' => '+5511999991111',
        ];

        $result = $service->dispatch('sms', $lead, ['message' => 'Oi {id}'], ['idempotency_key' => 'k-test']);

        $this->assertSame('success', $result['status']);
        $this->assertSame('provider-123', $result['external_ref']);

        Http::assertSentCount(2);
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://example.test/sms'
                && $request->header('X-Idempotency-Key')[0] === 'k-test';
        });
    }

    public function test_sms_dispatch_retries_on_retryable_exception_pattern(): void
    {
        config()->set('automation_dispatch.sms.webhook_url', 'https://example.test/sms');
        config()->set('automation_dispatch.sms.timeout_seconds', 2);
        config()->set('automation_dispatch.sms.retry_attempts', 3);
        config()->set('automation_dispatch.sms.retry_backoff_ms', '1,1,1');
        config()->set('automation_dispatch.sms.retry_on_exceptions', true);
        config()->set('automation_dispatch.sms.retryable_exception_patterns', 'timeout,connection refused');

        $attempt = 0;
        Http::fake(function () use (&$attempt) {
            $attempt++;
            if ($attempt === 1) {
                throw new \RuntimeException('timeout from provider');
            }

            return Http::response(['id' => 'provider-after-timeout'], 200);
        });

        $service = new AutomationChannelDispatchService();
        $lead = (object) [
            'id' => 99,
            'lead_source_id' => 10,
            'phone_e164' => '+5511999991111',
        ];

        $result = $service->dispatch('sms', $lead, ['message' => 'Oi {id}'], ['idempotency_key' => 'k-timeout']);

        $this->assertSame('success', $result['status']);
        $this->assertSame('provider-after-timeout', $result['external_ref']);
        $this->assertSame(2, (int) ($result['response']['attempt'] ?? 0));
    }

    public function test_sms_dispatch_does_not_retry_on_non_retryable_exception_pattern(): void
    {
        config()->set('automation_dispatch.sms.webhook_url', 'https://example.test/sms');
        config()->set('automation_dispatch.sms.timeout_seconds', 2);
        config()->set('automation_dispatch.sms.retry_attempts', 3);
        config()->set('automation_dispatch.sms.retry_backoff_ms', '1,1,1');
        config()->set('automation_dispatch.sms.retry_on_exceptions', true);
        config()->set('automation_dispatch.sms.retryable_exception_patterns', 'timeout,connection refused');

        Http::fake(function () {
            throw new \RuntimeException('invalid payload shape');
        });

        $service = new AutomationChannelDispatchService();
        $lead = (object) [
            'id' => 100,
            'lead_source_id' => 10,
            'phone_e164' => '+5511999991111',
        ];

        $result = $service->dispatch('sms', $lead, ['message' => 'Oi {id}'], ['idempotency_key' => 'k-invalid']);

        $this->assertSame('failed', $result['status']);
        $this->assertSame('webhook_exception', (string) ($result['response']['mode'] ?? ''));
        $this->assertSame(1, (int) ($result['response']['attempt'] ?? 0));
    }
}
