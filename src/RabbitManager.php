<?php

namespace Pushin\LaravelRabbit;

use Pushin\LaravelRabbit\Contracts\AmqpConnectionFactory;
use Pushin\LaravelRabbit\Exceptions\ConfigurationException;

class RabbitManager
{
    /** @var array<string, RabbitConnection> */
    private array $connections = [];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly array $config,
        private readonly AmqpConnectionFactory $connectionFactory,
    ) {
    }

    public function defaultConnectionName(): string
    {
        return (string) data_get($this->config, 'connection', 'default');
    }

    public function connection(?string $name = null): RabbitConnection
    {
        $name ??= $this->defaultConnectionName();

        if (! isset($this->connections[$name])) {
            $this->connections[$name] = new RabbitConnection(
                $name,
                $this->connectionConfig($name),
                $this->connectionFactory,
            );
        }

        return $this->connections[$name];
    }

    public function disconnect(?string $name = null): void
    {
        if ($name !== null) {
            if (isset($this->connections[$name])) {
                $this->connections[$name]->disconnect();
            }

            unset($this->connections[$name]);

            return;
        }

        foreach ($this->connections as $connection) {
            $connection->disconnect();
        }

        $this->connections = [];
    }

    public function config(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->config;
        }

        return data_get($this->config, $key, $default);
    }

    /**
     * @return array<string, mixed>
     */
    private function connectionConfig(string $name): array
    {
        $connection = data_get($this->config, "connections.{$name}");

        if (! is_array($connection)) {
            throw new ConfigurationException(sprintf('RabbitMQ connection [%s] is not configured.', $name));
        }

        return $connection;
    }
}
