<?php

namespace Pushin\LaravelRabbit\Contracts;

interface AmqpConnectionFactory
{
    /**
     * @param array<string, mixed> $config
     */
    public function connect(string $name, array $config): AmqpConnection;
}
