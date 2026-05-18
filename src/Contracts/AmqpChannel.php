<?php

namespace Pushin\LaravelRabbit\Contracts;

use PhpAmqpLib\Message\AMQPMessage;

interface AmqpChannel
{
    public function native(): mixed;

    public function close(): void;

    public function hasCallbacks(): bool;

    /**
     * @param array<int, mixed>|null $allowedMethods
     */
    public function wait(?array $allowedMethods = null, bool $nonBlocking = false, int|float|null $timeout = null): mixed;

    /**
     * @param array<string, mixed> $arguments
     */
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
    ): mixed;

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array{0: string, 1: int, 2: int}|null
     */
    public function queueDeclare(
        string $queue = '',
        bool $passive = false,
        bool $durable = true,
        bool $exclusive = false,
        bool $autoDelete = false,
        bool $nowait = false,
        array $arguments = [],
        ?int $ticket = null,
    ): ?array;

    /**
     * @param array<string, mixed> $arguments
     */
    public function queueBind(
        string $queue,
        string $exchange,
        string $routingKey = '',
        bool $nowait = false,
        array $arguments = [],
        ?int $ticket = null,
    ): mixed;

    public function queuePurge(string $queue = '', bool $nowait = false, ?int $ticket = null): int|string|null;

    public function basicPublish(
        AMQPMessage $message,
        string $exchange = '',
        string $routingKey = '',
        bool $mandatory = false,
        bool $immediate = false,
        ?int $ticket = null,
    ): void;

    public function basicGet(string $queue = '', bool $noAck = false, ?int $ticket = null): ?AMQPMessage;

    /**
     * @param array<string, mixed> $arguments
     */
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
    ): string;

    public function basicCancel(string $consumerTag, bool $nowait = false, bool $noreturn = false): mixed;

    public function basicQos(int $prefetchSize = 0, int $prefetchCount = 0, bool $global = false): mixed;

    public function confirmSelect(bool $nowait = false): mixed;

    public function waitForPendingAcks(int|float $timeout = 0): void;

    public function waitForPendingAcksReturns(int|float $timeout = 0): void;

    public function setReturnListener(callable $callback): void;

    public function setAckHandler(callable $callback): void;

    public function setNackHandler(callable $callback): void;
}
