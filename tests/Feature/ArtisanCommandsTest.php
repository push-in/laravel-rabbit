<?php

namespace Pushin\LaravelRabbit\Tests\Feature;

use PhpAmqpLib\Message\AMQPMessage;
use Pushin\LaravelRabbit\Contracts\AmqpConnectionFactory;
use Pushin\LaravelRabbit\Contracts\RabbitManagementClient;
use Pushin\LaravelRabbit\RabbitManager;
use Pushin\LaravelRabbit\Tests\Fakes\FakeAmqpConnection;
use Pushin\LaravelRabbit\Tests\Fakes\FakeAmqpFactory;
use Pushin\LaravelRabbit\Tests\Fakes\FakeManagementClient;
use Pushin\LaravelRabbit\Tests\TestCase;

class ArtisanCommandsTest extends TestCase
{
    public function test_check_command_verifies_connection_and_optional_queue(): void
    {
        $fake = $this->bindFakeRabbit();

        $this->artisan('rabbitmq:check', ['--queue' => 'orders'])
            ->expectsOutput('RabbitMQ connection [default] is reachable.')
            ->expectsOutput('Queue [orders] exists.')
            ->assertSuccessful();

        $this->assertSame('orders', $fake->connection->channel->queueDeclarations[0]['queue']);
        $this->assertTrue($fake->connection->channel->queueDeclarations[0]['passive']);
    }

    public function test_setup_command_declares_topology_and_laravel_queue(): void
    {
        $fake = $this->bindFakeRabbit();

        $this->artisan('rabbitmq:setup', ['--queue' => 'orders'])
            ->expectsOutput('RabbitMQ topology for connection [default] is ready.')
            ->expectsOutput('Laravel queue [orders] is ready.')
            ->assertSuccessful();

        $queues = array_column($fake->connection->channel->queueDeclarations, 'queue');

        $this->assertContains('orders', $queues);
    }

    public function test_stats_command_reports_available_queue_metrics(): void
    {
        $this->bindFakeRabbit();

        $this->artisan('rabbitmq:stats', ['queue' => 'orders'])
            ->expectsOutputToContain('pending')
            ->expectsOutputToContain('total')
            ->assertSuccessful();
    }

    public function test_stats_command_reports_management_api_metrics_when_enabled(): void
    {
        $this->bindFakeRabbit();
        $this->app->instance(RabbitManagementClient::class, new FakeManagementClient(queueStats: [
            "/\0orders" => [
                'messages' => 9,
                'messages_ready' => 6,
                'messages_unacknowledged' => 3,
            ],
        ], queueLists: [
            '/' => [
                ['name' => 'laravel.delay.orders.30', 'messages' => 4],
            ],
        ]));

        $this->artisan('rabbitmq:stats', ['queue' => 'orders'])
            ->expectsOutputToContain('management_api | enabled')
            ->expectsOutputToContain('reserved')
            ->assertSuccessful();
    }

    public function test_management_command_shows_queue_metrics(): void
    {
        $this->app->instance(RabbitManagementClient::class, new FakeManagementClient(queueStats: [
            "/\0orders" => [
                'name' => 'orders',
                'vhost' => '/',
                'messages' => 9,
                'messages_ready' => 6,
                'messages_unacknowledged' => 3,
                'consumers' => 2,
                'state' => 'running',
            ],
        ]));

        $this->artisan('rabbitmq:management', ['--queue' => 'orders'])
            ->expectsOutputToContain('messages_ready')
            ->expectsOutputToContain('messages_unacknowledged')
            ->assertSuccessful();
    }

    public function test_purge_command_clears_a_queue_when_forced(): void
    {
        $fake = $this->bindFakeRabbit();
        $fake->connection->channel->queuedMessages[] = new AMQPMessage('payload');

        $this->artisan('rabbitmq:purge', ['queue' => 'orders', '--force' => true])
            ->expectsOutput('Purged 1 message(s) from queue [orders].')
            ->assertSuccessful();

        $this->assertSame('orders', $fake->connection->channel->purges[0]['queue']);
    }

    public function test_install_command_outputs_required_environment_variables(): void
    {
        $this->artisan('rabbitmq:install')
            ->expectsOutput('Laravel Rabbit configuration published.')
            ->expectsOutput('QUEUE_CONNECTION=rabbitmq')
            ->expectsOutput('RABBITMQ_HOST=127.0.0.1')
            ->assertSuccessful();
    }

    public function test_consume_test_command_publishes_and_consumes_a_round_trip_message(): void
    {
        $fake = $this->bindFakeRabbit();
        $fake->connection->channel->loopbackPublishes = true;

        $this->artisan('rabbitmq:consume-test', ['--queue' => 'healthcheck'])
            ->expectsOutput('RabbitMQ consume test passed on queue [healthcheck].')
            ->expectsOutputToContain('Round-trip message')
            ->assertSuccessful();

        $this->assertSame('healthcheck', $fake->connection->channel->queueDeclarations[0]['queue']);
        $this->assertSame('healthcheck', $fake->connection->channel->published[0]['routingKey']);
    }

    public function test_doctor_command_runs_configuration_connection_driver_and_round_trip_checks(): void
    {
        $fake = $this->bindFakeRabbit();
        $fake->connection->channel->loopbackPublishes = true;

        config()->set('queue.default', 'rabbitmq');

        $this->artisan('rabbitmq:doctor', ['--queue' => 'doctor'])
            ->expectsOutput('Running RabbitMQ diagnostics for connection [default].')
            ->expectsOutput('[ok] Connection configuration found.')
            ->expectsOutput('[ok] Security policy passed.')
            ->expectsOutput('[ok] QUEUE_CONNECTION is rabbitmq.')
            ->expectsOutput('[ok] Laravel queue driver [rabbitmq] is registered.')
            ->expectsOutput('[ok] RabbitMQ connection opened.')
            ->expectsOutput('[ok] Publish/consume round-trip passed.')
            ->expectsOutput('RabbitMQ diagnostics passed.')
            ->assertSuccessful();
    }

    private function bindFakeRabbit(): FakeAmqpFactory
    {
        $fake = new FakeAmqpFactory(new FakeAmqpConnection());

        $this->app->instance(AmqpConnectionFactory::class, $fake);
        $this->app->forgetInstance(RabbitManager::class);

        return $fake;
    }
}
