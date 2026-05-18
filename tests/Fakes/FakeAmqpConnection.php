<?php

namespace Pushin\LaravelRabbit\Tests\Fakes;

use Pushin\LaravelRabbit\Contracts\AmqpChannel;
use Pushin\LaravelRabbit\Contracts\AmqpConnection;

class FakeAmqpConnection implements AmqpConnection
{
    public bool $connected = true;

    public function __construct(public FakeAmqpChannel $channel = new FakeAmqpChannel())
    {
    }

    public function native(): mixed
    {
        return null;
    }

    public function channel(?int $channelId = null): AmqpChannel
    {
        $this->channel->channelIds[] = $channelId;

        return $this->channel;
    }

    public function reconnect(): void
    {
        $this->connected = true;
    }

    public function close(): void
    {
        $this->connected = false;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }
}
