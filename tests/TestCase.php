<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\Concurrency\ConcurrencyEnvironment;
use Tests\Support\Concurrency\ConcurrencyTestCase;

abstract class TestCase extends BaseTestCase
{
    protected ?ConcurrencyEnvironment $concurrencyEnvironment = null;

    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        if (! $this->runsConcurrentWorkers()) {
            return;
        }

        $this->connectionsToTransact = [];
        RefreshDatabaseState::$migrated = false;
        $this->concurrencyEnvironment = new ConcurrencyEnvironment;
        $this->concurrencyEnvironment->apply($this->app);
    }

    protected function tearDown(): void
    {
        $environment = $this->concurrencyEnvironment;
        $this->concurrencyEnvironment = null;

        parent::tearDown();

        $environment?->cleanup();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function usesInnoDbRowLocks(): bool
    {
        return $this->concurrencyEnvironment?->usesRowLocks() ?? false;
    }

    protected function runsConcurrentWorkers(): bool
    {
        return str_contains(static::class, 'Feature\\Concurrency')
            || is_a(static::class, ConcurrencyTestCase::class, true);
    }
}
