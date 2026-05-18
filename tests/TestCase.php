<?php

namespace Pushin\LaravelRabbit\Tests;

use Mockery;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Pushin\LaravelRabbit\LaravelRabbitServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * Compatibility property for older Testbench releases paired with newer Laravel 11 patches.
     *
     * @var \Illuminate\Testing\TestResponse|null
     */
    public static $latestResponse;

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
