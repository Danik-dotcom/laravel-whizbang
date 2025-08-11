<?php

declare(strict_types=1);

namespace Ludovicguenet\Whizbang\Tests;

use Ludovicguenet\Whizbang\WhizbangServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [WhizbangServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Using sqlite memory for speed; note raw SHOW statements in core are MySQL specific,
        // so tests avoid hitting those paths.
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
