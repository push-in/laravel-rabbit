<?php

namespace Pushin\LaravelRabbit\Tests\Unit;

use Mockery;
use PhpAmqpLib\Message\AMQPMessage;
use Pushin\LaravelRabbit\Exceptions\SecurityException;
use Pushin\LaravelRabbit\RabbitConnection;
use Pushin\LaravelRabbit\Tests\Fakes\FakeAmqpChannel;
use Pushin\LaravelRabbit\Tests\Fakes\FakeAmqpConnection;
use Pushin\LaravelRabbit\Tests\Fakes\FakeAmqpFactory;
use Pushin\LaravelRabbit\ValueObjects\ConsumerResult;
use Pushin\LaravelRabbit\ValueObjects\PublishedMessage;
use PHPUnit\Framework\TestCase;

class RabbitConnectionTest extends TestCase
{
    public function test_it_publishes_messages_with_confirms(): void
    {
        $channel = new FakeAmqpChannel();
        $connection = $this->connection($channel, [
            'message' => [
                'content_type' => 'text/plain',
                'delivery_mode' => 2,
                'headers' => ['source' => 'test'],
            ],
            'publish' => [
                'exchange' => 'events',
                'routing_key' => 'orders.created',
                'mandatory' => true,
            ],
            'publisher_confirms' => [
                'enabled' => true,
                'wait' => true,
                'timeout' => 4.5,
            ],
        ]);

        $result = $connection->publish('payload');

        $this->assertInstanceOf(PublishedMessage::class, $result);
        $this->assertSame('events', $result->exchange);
        $this->assertSame('orders.created', $result->routingKey);
        $this->assertTrue($result->confirmed);
        $this->assertCount(1, $channel->confirmSelects);
        $this->assertSame([4.5], $channel->ackWaits);
        $this->assertSame('payload', $channel->published[0]['message']->getBody());
        $this->assertSame('events', $channel->published[0]['exchange']);
        $this->assertSame('orders.created', $channel->published[0]['routingKey']);
        $this->assertTrue($channel->published[0]['mandatory']);
        $this->assertSame('text/plain', $channel->published[0]['message']->get('content_type'));
    }

    public function test_confirm_select_is_idempotent_for_repeated_publishes_on_the_same_channel(): void
    {
        $channel = new FakeAmqpChannel();
        $connection = $this->connection($channel);

        $connection->publish('first', options: [
            'prepare_channel' => false,
            'confirm_select' => true,
        ]);
        $connection->publish('second', options: [
            'prepare_channel' => false,
            'confirm_select' => true,
        ]);

        $this->assertCount(1, $channel->confirmSelects);
        $this->assertCount(2, $channel->published);
        $this->assertSame([5.0, 5.0], $channel->ackWaits);
    }

    public function test_confirm_select_is_tracked_per_channel(): void
    {
        $channel = new FakeAmqpChannel();
        $connection = $this->connection($channel);

        $connection->publish('first', options: [
            'channel_id' => 1,
            'prepare_channel' => false,
            'confirm_select' => true,
        ]);
        $connection->publish('second', options: [
            'channel_id' => 2,
            'prepare_channel' => false,
            'confirm_select' => true,
        ]);

        $this->assertCount(2, $channel->confirmSelects);
        $this->assertSame([1, 2], $channel->channelIds);
    }

    public function test_it_publishes_json_messages(): void
    {
        $channel = new FakeAmqpChannel();
        $connection = $this->connection($channel);

        $connection->publishJson(['order_id' => 10], 'orders.created', 'events');

        $message = $channel->published[0]['message'];

        $this->assertSame('{"order_id":10}', $message->getBody());
        $this->assertSame('application/json', $message->get('content_type'));
    }

    public function test_it_declares_configured_topology_when_channel_is_prepared(): void
    {
        $channel = new FakeAmqpChannel();
        $connection = $this->connection($channel, [
            'topology' => [
                'auto_declare' => true,
            ],
            'exchange' => [
                'name' => 'events',
                'type' => 'topic',
                'durable' => true,
                'declare' => true,
            ],
            'queue' => [
                'name' => 'orders',
                'durable' => true,
                'declare' => true,
                'bindings' => [
                    ['exchange' => 'events', 'routing_key' => 'orders.*'],
                ],
            ],
        ]);

        $connection->channel();

        $this->assertSame('events', $channel->exchangeDeclarations[0]['exchange']);
        $this->assertSame('topic', $channel->exchangeDeclarations[0]['type']);
        $this->assertSame('orders', $channel->queueDeclarations[0]['queue']);
        $this->assertSame('orders', $channel->bindings[0]['queue']);
        $this->assertSame('events', $channel->bindings[0]['exchange']);
        $this->assertSame('orders.*', $channel->bindings[0]['routingKey']);
    }

    public function test_manual_setup_topology_does_not_double_declare_when_auto_declare_is_enabled(): void
    {
        $channel = new FakeAmqpChannel();
        $connection = $this->connection($channel, [
            'topology' => [
                'auto_declare' => true,
            ],
            'exchange' => [
                'name' => 'events',
                'declare' => true,
            ],
            'queue' => [
                'name' => 'orders',
                'declare' => true,
                'bindings' => [
                    ['exchange' => 'events', 'routing_key' => 'orders.*'],
                ],
            ],
        ]);

        $connection->setupTopology();

        $this->assertCount(1, $channel->exchangeDeclarations);
        $this->assertCount(1, $channel->queueDeclarations);
        $this->assertCount(1, $channel->bindings);
    }

    public function test_it_rejects_messages_larger_than_the_security_limit(): void
    {
        $channel = new FakeAmqpChannel();
        $connection = $this->connection($channel, [
            'security' => [
                'max_message_size' => 3,
            ],
        ]);

        $this->expectException(SecurityException::class);

        $connection->publish('abcd');
    }

    public function test_it_consumes_messages_and_auto_acks_successful_callbacks(): void
    {
        $channel = new FakeAmqpChannel();
        $message = Mockery::mock(AMQPMessage::class);
        $message->shouldReceive('ack')->once()->withNoArgs();
        $channel->queuedMessages[] = $message;
        $connection = $this->connection($channel);

        $count = $connection->consume(
            static fn (AMQPMessage $message): null => null,
            options: ['max_messages' => 1],
        );

        $this->assertSame(1, $count);
        $this->assertSame('fake-consumer', $channel->cancels[0]['consumerTag']);
    }

    public function test_it_can_nack_messages_when_callback_returns_false(): void
    {
        $channel = new FakeAmqpChannel();
        $message = Mockery::mock(AMQPMessage::class);
        $message->shouldReceive('nack')->once()->with(true);
        $channel->queuedMessages[] = $message;
        $connection = $this->connection($channel);

        $connection->consume(
            static fn (AMQPMessage $message): bool => false,
            options: [
                'max_messages' => 1,
                'nack_on_false_requeue' => true,
            ],
        );

        $this->assertSame('fake-consumer', $channel->cancels[0]['consumerTag']);
    }

    public function test_it_supports_explicit_consumer_results(): void
    {
        $channel = new FakeAmqpChannel();
        $message = Mockery::mock(AMQPMessage::class);
        $message->shouldReceive('reject')->once()->with(false);
        $channel->queuedMessages[] = $message;
        $connection = $this->connection($channel);

        $connection->consume(
            static fn (AMQPMessage $message): ConsumerResult => ConsumerResult::reject(),
            options: ['max_messages' => 1],
        );

        $this->assertSame('fake-consumer', $channel->cancels[0]['consumerTag']);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $config
     */
    private function connection(FakeAmqpChannel $channel, array $config = []): RabbitConnection
    {
        $defaults = [
            'topology' => [
                'auto_declare' => false,
            ],
            'qos' => [
                'enabled' => false,
                'prefetch_size' => 0,
                'prefetch_count' => 10,
                'global' => false,
            ],
            'publisher_confirms' => [
                'enabled' => true,
                'wait' => true,
                'timeout' => 5.0,
            ],
            'message' => [
                'content_type' => 'text/plain',
                'delivery_mode' => 2,
            ],
            'publish' => [
                'exchange' => '',
                'routing_key' => 'default',
                'mandatory' => false,
                'immediate' => false,
                'ticket' => null,
            ],
            'queue' => [
                'name' => 'default',
            ],
            'consumer' => [
                'tag' => '',
                'no_local' => false,
                'no_ack' => false,
                'exclusive' => false,
                'nowait' => false,
                'ticket' => null,
                'arguments' => [],
                'wait_timeout' => 0.01,
                'max_messages' => null,
                'stop_when_empty' => true,
                'ack_on_success' => true,
                'nack_on_false' => true,
                'nack_on_false_requeue' => false,
                'reject_on_exception' => true,
                'reject_on_exception_requeue' => false,
            ],
        ];

        return new RabbitConnection(
            'default',
            array_replace_recursive($defaults, $config),
            new FakeAmqpFactory(new FakeAmqpConnection($channel)),
        );
    }
}
