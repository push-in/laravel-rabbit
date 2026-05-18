<?php

namespace Pushin\LaravelRabbit\Exceptions;

use Throwable;

class ConnectionException extends LaravelRabbitException
{
    public static function unableToConnect(string $name, Throwable $previous): self
    {
        return new self(
            sprintf('Unable to connect to RabbitMQ connection [%s]: %s', $name, $previous->getMessage()),
            previous: $previous,
        );
    }
}
