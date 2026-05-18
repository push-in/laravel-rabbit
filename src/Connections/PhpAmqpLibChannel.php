<?php

namespace Pushin\LaravelRabbit\Connections;

use PhpAmqpLib\Channel\AMQPChannel as NativeChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Pushin\LaravelRabbit\Contracts\AmqpChannel;

class PhpAmqpLibChannel implements AmqpChannel
{
    public function __construct(private readonly NativeChannel $channel)
    {
    }

    public function native(): NativeChannel
    {
        return $this->channel;
    }

    public function close(): void
    {
        $this->channel->close();
    }

    public function hasCallbacks(): bool
    {
        return $this->channel->is_consuming();
    }

    public function wait(?array $allowedMethods = null, bool $nonBlocking = false, int|float|null $timeout = null): mixed
    {
        return $this->channel->wait($allowedMethods, $nonBlocking, $timeout);
    }

    public function exchangeDeclare(
        string $exchange,
        string $type = 'direct',
        bool $passive = false,
        bool $durable = true,
        bool $autoDelete = false,
        bool $internal = false,
        bool $nowait = false,
        array $arguments = [],
        ?int $ticket = null,
    ): mixed {
        return $this->channel->exchange_declare(
            $exchange,
            $type,
            $passive,
            $durable,
            $autoDelete,
            $internal,
            $nowait,
            $this->table($arguments),
            $ticket,
        );
    }

    public function queueDeclare(
        string $queue = '',
        bool $passive = false,
        bool $durable = true,
        bool $exclusive = false,
        bool $autoDelete = false,
        bool $nowait = false,
        array $arguments = [],
        ?int $ticket = null,
    ): ?array {
        return $this->channel->queue_declare(
            $queue,
            $passive,
            $durable,
            $exclusive,
            $autoDelete,
            $nowait,
            $this->table($arguments),
            $ticket,
        );
    }

    public function queueBind(
        string $queue,
        string $exchange,
        string $routingKey = '',
        bool $nowait = false,
        array $arguments = [],
        ?int $ticket = null,
    ): mixed {
        return $this->channel->queue_bind($queue, $exchange, $routingKey, $nowait, $this->table($arguments), $ticket);
    }

    public function queuePurge(string $queue = '', bool $nowait = false, ?int $ticket = null): int|string|null
    {
        return $this->channel->queue_purge($queue, $nowait, $ticket);
    }

    public function basicPublish(
        AMQPMessage $message,
        string $exchange = '',
        string $routingKey = '',
        bool $mandatory = false,
        bool $immediate = false,
        ?int $ticket = null,
    ): void {
        $this->channel->basic_publish($message, $exchange, $routingKey, $mandatory, $immediate, $ticket);
    }

    public function basicGet(string $queue = '', bool $noAck = false, ?int $ticket = null): ?AMQPMessage
    {
        return $this->channel->basic_get($queue, $noAck, $ticket);
    }

    public function basicConsume(
        string $queue = '',
        string $consumerTag = '',
        bool $noLocal = false,
        bool $noAck = false,
        bool $exclusive = false,
        bool $nowait = false,
        ?callable $callback = null,
        ?int $ticket = null,
        array $arguments = [],
    ): string {
        return $this->channel->basic_consume(
            $queue,
            $consumerTag,
            $noLocal,
            $noAck,
            $exclusive,
            $nowait,
            $callback,
            $ticket,
            $this->table($arguments),
        );
    }

    public function basicCancel(string $consumerTag, bool $nowait = false, bool $noreturn = false): mixed
    {
        return $this->channel->basic_cancel($consumerTag, $nowait, $noreturn);
    }

    public function basicQos(int $prefetchSize = 0, int $prefetchCount = 0, bool $global = false): mixed
    {
        return $this->channel->basic_qos($prefetchSize, $prefetchCount, $global);
    }

    public function confirmSelect(bool $nowait = false): mixed
    {
        return $this->channel->confirm_select($nowait);
    }

    public function waitForPendingAcks(int|float $timeout = 0): void
    {
        $this->channel->wait_for_pending_acks($timeout);
    }

    public function waitForPendingAcksReturns(int|float $timeout = 0): void
    {
        $this->channel->wait_for_pending_acks_returns($timeout);
    }

    public function setReturnListener(callable $callback): void
    {
        $this->channel->set_return_listener($callback);
    }

    public function setAckHandler(callable $callback): void
    {
        $this->channel->set_ack_handler($callback);
    }

    public function setNackHandler(callable $callback): void
    {
        $this->channel->set_nack_handler($callback);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function table(array $arguments): AMQPTable
    {
        return new AMQPTable($arguments);
    }
}
