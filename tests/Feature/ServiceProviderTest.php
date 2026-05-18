<?php

namespace Pushin\LaravelRabbit\Tests\Feature;

use Pushin\LaravelRabbit\LaravelRabbit;
use Pushin\LaravelRabbit\Contracts\RabbitManagementClient;
use Pushin\LaravelRabbit\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    public function test_it_registers_the_package_singleton(): void
    {
        $this->assertInstanceOf(LaravelRabbit::class, app(LaravelRabbit::class));
        $this->assertSame(app(LaravelRabbit::class), app('laravel-rabbit'));
        $this->assertInstanceOf(RabbitManagementClient::class, app('laravel-rabbit.management'));
    }

    public function test_it_loads_default_configuration(): void
    {
        $rabbit = app(LaravelRabbit::class);

        $this->assertSame('default', $rabbit->connectionName());
        $this->assertSame('127.0.0.1', $rabbit->config('connections.default.host'));
        $this->assertSame(5672, $rabbit->config('connections.default.port'));
    }
}
