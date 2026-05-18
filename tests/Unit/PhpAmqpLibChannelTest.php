<?php

namespace Pushin\LaravelRabbit\Tests\Unit;

use Mockery;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Wire\AMQPTable;
use Pushin\LaravelRabbit\Connections\PhpAmqpLibChannel;
use PHPUnit\Framework\TestCase;

class PhpAmqpLibChannelTest extends TestCase
{
    public function test_it_converts_queue_arguments_to_amqp_table(): void
    {
        $convertedArguments = false;
        $native = Mockery::mock(AMQPChannel::class);
        $native->shouldReceive('queue_declare')
            ->once()
            ->withArgs(function (
                string $queue,
                bool $passive,
                bool $durable,
                bool $exclusive,
                bool $autoDelete,
                bool $nowait,
                mixed $arguments,
                ?int $ticket,
            ) use (&$convertedArguments): bool {
                $convertedArguments = $queue === 'delay.orders.2'
                    && $arguments instanceof AMQPTable
                    && $arguments->getNativeData()['x-message-ttl'] === 2000
                    && $arguments->getNativeData()['x-dead-letter-routing-key'] === 'orders'
                    && $ticket === null;

                return $convertedArguments;
            })
            ->andReturn(['delay.orders.2', 0, 0]);

        $channel = new PhpAmqpLibChannel($native);

        $channel->queueDeclare(
            'delay.orders.2',
            arguments: [
                'x-message-ttl' => 2000,
                'x-dead-letter-routing-key' => 'orders',
            ],
        );

        $this->assertTrue($convertedArguments);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
