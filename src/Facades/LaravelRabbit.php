<?php

namespace Pushin\LaravelRabbit\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string connectionName()
 * @method static \Pushin\LaravelRabbit\RabbitManager manager()
 * @method static \Pushin\LaravelRabbit\Contracts\RabbitManagementClient management()
 * @method static \Pushin\LaravelRabbit\RabbitConnection connection(string|null $name = null)
 * @method static \Pushin\LaravelRabbit\ValueObjects\PublishedMessage publish(string $body, string|null $routingKey = null, string|null $exchange = null, array $properties = [], array $options = [], string|null $connection = null)
 * @method static \Pushin\LaravelRabbit\ValueObjects\PublishedMessage publishJson(array|\JsonSerializable $payload, string|null $routingKey = null, string|null $exchange = null, array $properties = [], array $options = [], string|null $connection = null)
 * @method static \PhpAmqpLib\Message\AMQPMessage|null get(string|null $queue = null, bool $noAck = false, int|null $ticket = null, string|null $connection = null)
 * @method static int consume(callable $callback, string|null $queue = null, array $options = [], string|null $connection = null)
 * @method static void setupTopology(string|null $connection = null)
 * @method static void disconnect(string|null $connection = null)
 * @method static mixed config(string|null $key = null, mixed $default = null)
 *
 * @see \Pushin\LaravelRabbit\LaravelRabbit
 */
class LaravelRabbit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Pushin\LaravelRabbit\LaravelRabbit::class;
    }
}
