<?php

namespace Pushin\LaravelRabbit\Tests;

use Mockery;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Pushin\LaravelRabbit\LaravelRabbitServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LaravelRabbitServiceProvider::class,
        ];
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
