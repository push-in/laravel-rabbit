<?php

namespace Pushin\LaravelRabbit;

use JsonSerializable;
use PhpAmqpLib\Message\AMQPMessage;
use Pushin\LaravelRabbit\Contracts\RabbitManagementClient;
use Pushin\LaravelRabbit\ValueObjects\PublishedMessage;

class LaravelRabbit
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly RabbitManager $manager,
        private readonly array $config,
        private readonly RabbitManagementClient $management,
    ) {
    }

    public function connectionName(): string
    {
        return $this->manager->defaultConnectionName();
    }

    public function manager(): RabbitManager
    {
        return $this->manager;
    }

    public function management(): RabbitManagementClient
    {
        return $this->management;
    }

    public function connection(?string $name = null): RabbitConnection
    {
        return $this->manager->connection($name);
    }

    /**
     * @param array<string, mixed> $properties
     * @param array<string, mixed> $options
     */
    public function publish(
        string $body,
        ?string $routingKey = null,
        ?string $exchange = null,
        array $properties = [],
        array $options = [],
        ?string $connection = null,
    ): PublishedMessage {
        return $this->connection($connection)->publish($body, $routingKey, $exchange, $properties, $options);
    }

    /**
     * @param array<string, mixed>|JsonSerializable $payload
     * @param array<string, mixed> $properties
     * @param array<string, mixed> $options
     */
    public function publishJson(
        array|JsonSerializable $payload,
        ?string $routingKey = null,
        ?string $exchange = null,
        array $properties = [],
        array $options = [],
        ?string $connection = null,
    ): PublishedMessage {
        return $this->connection($connection)->publishJson($payload, $routingKey, $exchange, $properties, $options);
    }

    public function get(?string $queue = null, bool $noAck = false, ?int $ticket = null, ?string $connection = null): ?AMQPMessage
    {
        return $this->connection($connection)->get($queue, $noAck, $ticket);
    }

    /**
     * @param callable(AMQPMessage, RabbitConnection): mixed $callback
     * @param array<string, mixed> $options
     */
    public function consume(callable $callback, ?string $queue = null, array $options = [], ?string $connection = null): int
    {
        return $this->connection($connection)->consume($callback, $queue, $options);
    }

    public function setupTopology(?string $connection = null): void
    {
        $this->connection($connection)->setupTopology();
    }

    public function disconnect(?string $connection = null): void
    {
        $this->manager->disconnect($connection);
    }

    public function config(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->config;
        }

        return data_get($this->config, $key, $default);
    }
}
