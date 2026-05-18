<?php

namespace Pushin\LaravelRabbit\Connections;

use PhpAmqpLib\Connection\AbstractConnection;
use Pushin\LaravelRabbit\Contracts\AmqpChannel;
use Pushin\LaravelRabbit\Contracts\AmqpConnection;

class PhpAmqpLibConnection implements AmqpConnection
{
    public function __construct(private readonly AbstractConnection $connection)
    {
    }

    public function native(): AbstractConnection
    {
        return $this->connection;
    }

    public function channel(?int $channelId = null): AmqpChannel
    {
        return new PhpAmqpLibChannel($this->connection->channel($channelId));
    }

    public function reconnect(): void
    {
        $this->connection->reconnect();
    }

    public function close(): void
    {
        if ($this->connection->isConnected()) {
            $this->connection->close();
        }
    }

    public function isConnected(): bool
    {
        return $this->connection->isConnected();
    }
}
