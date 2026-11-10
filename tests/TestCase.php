<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Pages render the layouts, which call @vite/@fonts; tests must not depend on a built manifest.
        $this->withoutVite();
    }
}
