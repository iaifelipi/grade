<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Some production setups lock storage/framework/views (immutable bit) for hardening.
        // In tests, compile views into /tmp to avoid permission/immutability issues.
        $compiled = (string) env('VIEW_COMPILED_PATH', '');
        if ($compiled !== '') {
            if (!is_dir($compiled)) {
                @mkdir($compiled, 0775, true);
            }
            config(['view.compiled' => $compiled]);
        }
    }
}
