<?php

namespace Pushin\LaravelRabbit\Tests\Feature;

use Pushin\LaravelRabbit\Contracts\AmqpConnectionFactory;
use Pushin\LaravelRabbit\Queue\RabbitQueue;
use Pushin\LaravelRabbit\RabbitManager;
use Pushin\LaravelRabbit\Tests\Fakes\FakeAmqpConnection;
use Pushin\LaravelRabbit\Tests\Fakes\FakeAmqpFactory;
use Pushin\LaravelRabbit\Tests\Fixtures\RabbitQueuedJob;
use Pushin\LaravelRabbit\Tests\TestCase;

class LaravelQueueIntegrationTest extends TestCase
{
    public function test_it_registers_a_native_laravel_rabbitmq_queue_connection(): void
    {
        $fake = new FakeAmqpFactory(new FakeAmqpConnection());
        $this->app->instance(AmqpConnectionFactory::class, $fake);
        $this->app->forgetInstance(RabbitManager::class);

        config()->set('queue.default', 'rabbitmq');

        $connection = app('queue')->connection('rabbitmq');

        $this->assertInstanceOf(RabbitQueue::class, $connection);
    }

    public function test_laravel_dispatch_on_queue_publishes_to_rabbitmq(): void
    {
        $fake = new FakeAmqpFactory(new FakeAmqpConnection());
        $this->app->instance(AmqpConnectionFactory::class, $fake);
        $this->app->forgetInstance(RabbitManager::class);

        config()->set('queue.default', 'rabbitmq');
        config()->set('queue.connections.rabbitmq.exchange', 'jobs');

        $pending = RabbitQueuedJob::dispatch()->onQueue('critical');
        unset($pending);

        $channel = $fake->connection->channel;

        $this->assertSame('critical', $channel->queueDeclarations[0]['queue']);
        $this->assertSame('jobs', $channel->published[0]['exchange']);
        $this->assertSame('critical', $channel->published[0]['routingKey']);
    }
}
