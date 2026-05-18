<?php

namespace Pushin\LaravelRabbit\Contracts;

interface AmqpConnection
{
    public function native(): mixed;

    public function channel(?int $channelId = null): AmqpChannel;

    public function reconnect(): void;

    public function close(): void;

    public function isConnected(): bool;
}
