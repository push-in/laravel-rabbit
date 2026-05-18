<?php

namespace Pushin\LaravelRabbit\Tests\Unit;

use PhpAmqpLib\Connection\AMQPConnectionConfig;
use Pushin\LaravelRabbit\Connections\ConnectionConfigFactory;
use Pushin\LaravelRabbit\Exceptions\SecurityException;
use Pushin\LaravelRabbit\Support\SecurityPolicy;
use PHPUnit\Framework\TestCase;

class ConnectionConfigFactoryTest extends TestCase
{
    public function test_it_builds_connection_configs_for_failover_hosts(): void
    {
        $configs = $this->factory()->makeAll('default', [
            'user' => 'app',
            'password' => 'secret',
            'vhost' => '/events',
            'heartbeat' => 30,
            'connection_name' => 'worker',
            'ssl' => [
                'enabled' => true,
                'verify_peer' => true,
                'verify_peer_name' => true,
                'crypto_method' => 'STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT',
            ],
            'hosts' => [
                ['host' => 'rabbit-a.local', 'port' => 5671],
                ['host' => 'rabbit-b.local', 'port' => 5672],
            ],
        ]);

        $this->assertCount(2, $configs);
        $this->assertSame('rabbit-a.local', $configs[0]->getHost());
        $this->assertSame(5671, $configs[0]->getPort());
        $this->assertSame('rabbit-b.local', $configs[1]->getHost());
        $this->assertSame('/events', $configs[1]->getVhost());
        $this->assertSame(30, $configs[0]->getHeartbeat());
        $this->assertSame('worker', $configs[0]->getConnectionName());
        $this->assertTrue($configs[0]->isSecure());
        $this->assertSame(STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT, $configs[0]->getSslCryptoMethod());
    }

    public function test_it_rejects_insecure_tls_by_default(): void
    {
        $this->expectException(SecurityException::class);

        $this->factory()->makeAll('default', [
            'host' => '127.0.0.1',
            'ssl' => [
                'enabled' => true,
                'verify_peer' => false,
                'verify_peer_name' => true,
            ],
        ]);
    }

    public function test_it_allows_insecure_tls_only_when_explicitly_enabled(): void
    {
        $configs = $this->factory()->makeAll('default', [
            'host' => '127.0.0.1',
            'ssl' => [
                'enabled' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
            'security' => [
                'allow_insecure_tls' => true,
            ],
        ]);

        $this->assertInstanceOf(AMQPConnectionConfig::class, $configs[0]);
        $this->assertFalse($configs[0]->getSslVerify());
        $this->assertFalse($configs[0]->getSslVerifyName());
    }

    public function test_it_rejects_guest_user_on_remote_hosts(): void
    {
        $this->expectException(SecurityException::class);

        $this->factory()->makeAll('default', [
            'host' => 'rabbit.example.com',
            'user' => 'guest',
            'password' => 'guest',
        ]);
    }

    public function test_it_redacts_sensitive_configuration_values(): void
    {
        $sanitized = (new SecurityPolicy())->sanitize([
            'password' => 'secret',
            'publish' => [
                'routing_key' => 'orders.created',
            ],
            'ssl' => [
                'passphrase' => 'cert-secret',
                'local_pk' => '/secret/client.key',
            ],
            'queue' => [
                'signing_key' => 'queue-secret',
            ],
        ]);

        $this->assertSame('[redacted]', $sanitized['password']);
        $this->assertSame('[redacted]', $sanitized['ssl']['passphrase']);
        $this->assertSame('[redacted]', $sanitized['ssl']['local_pk']);
        $this->assertSame('[redacted]', $sanitized['queue']['signing_key']);
        $this->assertSame('orders.created', $sanitized['publish']['routing_key']);
    }

    private function factory(): ConnectionConfigFactory
    {
        return new ConnectionConfigFactory(new SecurityPolicy());
    }
}
