<?php

namespace Tests\Unit\LeadsVault;

use App\Support\LeadsVault\BulkTaskPayloadValidator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class BulkTaskPayloadValidatorTest extends TestCase
{
    public function test_accepts_update_fields_with_non_empty_updates(): void
    {
        $validator = new BulkTaskPayloadValidator();

        $validator->assertValid(
            'selected_ids',
            ['ids' => [1, 2]],
            'update_fields',
            ['updates' => ['lifecycle_stage' => 'reativacao']]
        );

        $this->assertTrue(true);
    }

    public function test_rejects_update_fields_without_updates(): void
    {
        $validator = new BulkTaskPayloadValidator();

        $this->expectException(InvalidArgumentException::class);
        $validator->assertValid(
            'selected_ids',
            ['ids' => [1]],
            'update_fields',
            []
        );
    }

    public function test_accepts_set_next_action_with_non_negative_days(): void
    {
        $validator = new BulkTaskPayloadValidator();

        $validator->assertValid(
            'filtered',
            ['filters' => ['entity_type' => 'lead']],
            'set_next_action',
            ['days' => 7]
        );

        $this->assertTrue(true);
    }

    public function test_rejects_set_next_action_with_negative_days(): void
    {
        $validator = new BulkTaskPayloadValidator();

        $this->expectException(InvalidArgumentException::class);
        $validator->assertValid(
            'filtered',
            ['filters' => []],
            'set_next_action',
            ['days' => -1]
        );
    }

    public function test_accepts_set_consent_with_valid_channel_and_status(): void
    {
        $validator = new BulkTaskPayloadValidator();

        $validator->assertValid(
            'selected_ids',
            ['ids' => [10]],
            'set_consent',
            ['channel' => 'email', 'status' => 'granted']
        );

        $this->assertTrue(true);
    }

    public function test_rejects_set_consent_with_invalid_channel(): void
    {
        $validator = new BulkTaskPayloadValidator();

        $this->expectException(InvalidArgumentException::class);
        $validator->assertValid(
            'selected_ids',
            ['ids' => [10]],
            'set_consent',
            ['channel' => 'push', 'status' => 'granted']
        );
    }

    public function test_rejects_set_consent_with_invalid_status(): void
    {
        $validator = new BulkTaskPayloadValidator();

        $this->expectException(InvalidArgumentException::class);
        $validator->assertValid(
            'selected_ids',
            ['ids' => [10]],
            'set_consent',
            ['channel' => 'email', 'status' => 'pending']
        );
    }
}
