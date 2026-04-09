<?php

namespace Pacificdev\DevClaw\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Pacificdev\DevClaw\DevClawServiceProvider;

class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [DevClawServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('devclaw.enabled', true);
        $app['config']->set('devclaw.token', 'test-token-abc123');
        $app['config']->set('devclaw.log_requests', false);
    }
}
