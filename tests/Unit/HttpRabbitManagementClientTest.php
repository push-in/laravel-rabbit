<?php

namespace Pushin\LaravelRabbit\Tests\Unit;

use Pushin\LaravelRabbit\Exceptions\ConfigurationException;
use Pushin\LaravelRabbit\Exceptions\SecurityException;
use Pushin\LaravelRabbit\Management\HttpRabbitManagementClient;
use PHPUnit\Framework\TestCase;

class HttpRabbitManagementClientTest extends TestCase
{
    public function test_it_is_disabled_by_default(): void
    {
        $client = new HttpRabbitManagementClient([]);

        $this->assertFalse($client->enabled());
        $this->assertNull($client->overview());
    }

    public function test_it_rejects_unsupported_management_api_schemes(): void
    {
        $this->expectException(ConfigurationException::class);

        new HttpRabbitManagementClient([
            'scheme' => 'ftp',
        ]);
    }

    public function test_it_rejects_insecure_management_tls_by_default(): void
    {
        $this->expectException(SecurityException::class);

        new HttpRabbitManagementClient([
            'enabled' => true,
            'scheme' => 'https',
            'verify_tls' => false,
        ]);
    }

    public function test_it_rejects_guest_user_on_remote_management_hosts(): void
    {
        $this->expectException(SecurityException::class);

        new HttpRabbitManagementClient([
            'enabled' => true,
            'host' => 'rabbit.example.com',
            'user' => 'guest',
            'password' => 'guest',
        ]);
    }

    public function test_it_allows_explicitly_insecure_management_tls_for_local_development(): void
    {
        $client = new HttpRabbitManagementClient([
            'enabled' => true,
            'scheme' => 'https',
            'host' => '127.0.0.1',
            'verify_tls' => false,
            'allow_insecure_tls' => true,
        ]);

        $this->assertTrue($client->enabled());
    }
}
