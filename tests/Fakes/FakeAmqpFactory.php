<?php

namespace Pushin\LaravelRabbit\Tests\Fakes;

use Pushin\LaravelRabbit\Contracts\AmqpConnection;
use Pushin\LaravelRabbit\Contracts\AmqpConnectionFactory;

class FakeAmqpFactory implements AmqpConnectionFactory
{
    /** @var array<int, array{name: string, config: array<string, mixed>}> */
    public array $connections = [];

    public function __construct(public FakeAmqpConnection $connection = new FakeAmqpConnection())
    {
    }

    public function connect(string $name, array $config): AmqpConnection
    {
        $this->connections[] = compact('name', 'config');

        return $this->connection;
    }
}
