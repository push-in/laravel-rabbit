<?php

namespace Pushin\LaravelRabbit;

use JsonSerializable;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use Pushin\LaravelRabbit\Contracts\AmqpChannel;
use Pushin\LaravelRabbit\Contracts\AmqpConnection;
use Pushin\LaravelRabbit\Contracts\AmqpConnectionFactory;
use Pushin\LaravelRabbit\Exceptions\ConfigurationException;
use Pushin\LaravelRabbit\Exceptions\SecurityException;
use Pushin\LaravelRabbit\Support\MessageFactory;
use Pushin\LaravelRabbit\ValueObjects\ConsumerResult;
use Pushin\LaravelRabbit\ValueObjects\PublishedMessage;
use Throwable;

class RabbitConnection
{
    private ?AmqpConnection $connection = null;

    /** @var array<string, AmqpChannel> */
    private array $channels = [];

    /** @var array<string, bool> */
    private array $preparedChannels = [];

    /** @var array<string, bool> */
    private array $confirmSelectedChannels = [];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly string $name,
        private readonly array $config,
        private readonly AmqpConnectionFactory $connectionFactory,
        private readonly MessageFactory $messageFactory = new MessageFactory(),
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function amqp(): AmqpConnection
    {
        return $this->connection();
    }

    public function connection(): AmqpConnection
    {
        if ($this->connection === null || ! $this->connection->isConnected()) {
            $this->channels = [];
            $this->preparedChannels = [];
            $this->confirmSelectedChannels = [];
            $this->connection = $this->connectionFactory->connect($this->name, $this->config);
        }

        return $this->connection;
    }

    public function reconnect(): AmqpConnection
    {
        $this->disconnect();

        return $this->connection();
    }

    public function disconnect(): void
    {
        if ($this->connection !== null) {
            $this->connection->close();
        }

        $this->connection = null;
        $this->channels = [];
        $this->preparedChannels = [];
        $this->confirmSelectedChannels = [];
    }

    public function isConnected(): bool
    {
        return $this->connection !== null && $this->connection->isConnected();
    }

    public function channel(?int $channelId = null, bool $prepare = true): AmqpChannel
    {
        $key = $this->channelKey($channelId);

        if (! isset($this->channels[$key])) {
            $this->channels[$key] = $this->connection()->channel($channelId);
        }

        if ($prepare) {
            $this->prepareChannel($key, $this->channels[$key]);
        }

        return $this->channels[$key];
    }

    public function setupTopology(?AmqpChannel $channel = null): void
    {
        if ($channel === null) {
            $channel = $this->channel();

            if ((bool) data_get($this->config, 'topology.auto_declare', true)) {
                return;
            }
        }

        foreach ($this->configuredExchanges() as $exchange) {
            if ((bool) data_get($exchange, 'declare', true)) {
                $this->declareExchange(null, $exchange, $channel);
            }
        }

        foreach ($this->configuredQueues() as $queue) {
            if ((bool) data_get($queue, 'declare', true)) {
                $this->declareQueue(null, $queue, $channel);
            }
        }

        foreach ($this->configuredBindings() as $binding) {
            $exchange = (string) data_get($binding, 'exchange', '');

            if ($exchange === '') {
                continue;
            }

            $this->bindQueue(
                data_get($binding, 'queue'),
                $exchange,
                (string) data_get($binding, 'routing_key', ''),
                $binding,
                $channel,
            );
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    public function declareExchange(?string $exchange = null, array $options = [], ?AmqpChannel $channel = null): mixed
    {
        $options = array_replace(data_get($this->config, 'exchange', []), $options);
        $exchange ??= (string) data_get($options, 'name', '');

        if ($exchange === '') {
            return null;
        }

        return ($channel ?? $this->channel())->exchangeDeclare(
            $exchange,
            (string) data_get($options, 'type', 'direct'),
            (bool) data_get($options, 'passive', false),
            (bool) data_get($options, 'durable', true),
            (bool) data_get($options, 'auto_delete', false),
            (bool) data_get($options, 'internal', false),
            (bool) data_get($options, 'nowait', false),
            (array) data_get($options, 'arguments', []),
            $this->nullableInt(data_get($options, 'ticket')),
        );
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array{0: string, 1: int, 2: int}|null
     */
    public function declareQueue(?string $queue = null, array $options = [], ?AmqpChannel $channel = null): ?array
    {
        $options = array_replace(data_get($this->config, 'queue', []), $options);
        $queue ??= (string) data_get($options, 'name', '');

        return ($channel ?? $this->channel())->queueDeclare(
            $queue,
            (bool) data_get($options, 'passive', false),
            (bool) data_get($options, 'durable', true),
            (bool) data_get($options, 'exclusive', false),
            (bool) data_get($options, 'auto_delete', false),
            (bool) data_get($options, 'nowait', false),
            (array) data_get($options, 'arguments', []),
            $this->nullableInt(data_get($options, 'ticket')),
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    public function bindQueue(
        ?string $queue = null,
        ?string $exchange = null,
        ?string $routingKey = null,
        array $options = [],
        ?AmqpChannel $channel = null,
    ): mixed {
        $queue ??= (string) data_get($options, 'queue', data_get($this->config, 'queue.name', ''));
        $exchange ??= (string) data_get($options, 'exchange', data_get($this->config, 'exchange.name', ''));
        $routingKey ??= (string) data_get($options, 'routing_key', data_get($this->config, 'publish.routing_key', ''));

        if ($queue === '' || $exchange === '') {
            throw new ConfigurationException('A queue binding requires both queue and exchange names.');
        }

        return ($channel ?? $this->channel())->queueBind(
            $queue,
            $exchange,
            $routingKey,
            (bool) data_get($options, 'nowait', false),
            (array) data_get($options, 'arguments', []),
            $this->nullableInt(data_get($options, 'ticket')),
        );
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
    ): PublishedMessage {
        $this->assertAllowedMessageSize($body);

        $channelId = $this->nullableInt(data_get($options, 'channel_id'));
        $channel = $this->channel($channelId, (bool) data_get($options, 'prepare_channel', true));
        $channelKey = $this->channelKey($channelId);
        $exchange ??= (string) data_get($options, 'exchange', data_get($this->config, 'publish.exchange', ''));
        $routingKey ??= (string) data_get($options, 'routing_key', data_get($this->config, 'publish.routing_key', ''));
        $properties = array_replace((array) data_get($this->config, 'message', []), $properties);
        $message = $this->messageFactory->make($body, $properties);
        $confirm = (bool) data_get($options, 'confirm', data_get($this->config, 'publisher_confirms.enabled', true));

        if ($confirm && (bool) data_get($options, 'confirm_select', ! (bool) data_get($this->config, 'publisher_confirms.enabled', true))) {
            $this->confirmSelectChannel($channelKey, $channel);
        }

        $channel->basicPublish(
            $message,
            $exchange,
            $routingKey,
            (bool) data_get($options, 'mandatory', data_get($this->config, 'publish.mandatory', false)),
            (bool) data_get($options, 'immediate', data_get($this->config, 'publish.immediate', false)),
            $this->nullableInt(data_get($options, 'ticket', data_get($this->config, 'publish.ticket'))),
        );

        $waitForConfirmation = $confirm && (bool) data_get($options, 'wait', data_get($this->config, 'publisher_confirms.wait', true));

        if ($waitForConfirmation) {
            $timeout = (float) data_get($options, 'confirm_timeout', data_get($this->config, 'publisher_confirms.timeout', 5.0));

            if ((bool) data_get($options, 'wait_for_returns', data_get($this->config, 'publisher_confirms.wait_for_returns', false))) {
                $channel->waitForPendingAcksReturns($timeout);
            } else {
                $channel->waitForPendingAcks($timeout);
            }
        }

        return new PublishedMessage($exchange, $routingKey, strlen($body), $properties, $waitForConfirmation);
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
    ): PublishedMessage {
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $this->publish(
            $body,
            $routingKey,
            $exchange,
            array_replace(['content_type' => 'application/json'], $properties),
            $options,
        );
    }

    public function get(?string $queue = null, bool $noAck = false, ?int $ticket = null): ?AMQPMessage
    {
        $queue ??= (string) data_get($this->config, 'queue.name', '');

        return $this->channel()->basicGet($queue, $noAck, $ticket);
    }

    /**
     * @param callable(AMQPMessage, self): mixed $callback
     * @param array<string, mixed> $options
     */
    public function consume(callable $callback, ?string $queue = null, array $options = []): int
    {
        $options = array_replace((array) data_get($this->config, 'consumer', []), $options);
        $queue ??= (string) data_get($options, 'queue', data_get($this->config, 'queue.name', ''));
        $channel = $this->channel($this->nullableInt(data_get($options, 'channel_id')));
        $messages = 0;
        $lastMessageAt = microtime(true);
        $noAck = (bool) data_get($options, 'no_ack', false);

        $consumerTag = $channel->basicConsume(
            $queue,
            (string) data_get($options, 'tag', ''),
            (bool) data_get($options, 'no_local', false),
            $noAck,
            (bool) data_get($options, 'exclusive', false),
            (bool) data_get($options, 'nowait', false),
            function (AMQPMessage $message) use ($callback, $options, $noAck, &$messages, &$lastMessageAt): void {
                $messages++;
                $lastMessageAt = microtime(true);

                try {
                    $result = $callback($message, $this);
                    $this->respondToMessage($message, $result, $options, $noAck);
                } catch (Throwable $exception) {
                    $this->respondToConsumerException($message, $options, $noAck);

                    throw $exception;
                }
            },
            $this->nullableInt(data_get($options, 'ticket')),
            (array) data_get($options, 'arguments', []),
        );

        try {
            while ($channel->hasCallbacks()) {
                $maxMessages = data_get($options, 'max_messages');

                if ($maxMessages !== null && $messages >= (int) $maxMessages) {
                    $channel->basicCancel($consumerTag);
                    break;
                }

                try {
                    $waitTimeout = data_get($options, 'wait_timeout');
                    $channel->wait(timeout: $waitTimeout === null ? null : (float) $waitTimeout);
                } catch (AMQPTimeoutException) {
                    if ($this->shouldStopOnIdle($lastMessageAt, $options)) {
                        $channel->basicCancel($consumerTag);
                        break;
                    }
                }
            }
        } finally {
            if ($channel->hasCallbacks() && data_get($options, 'cancel_on_exit', true)) {
                $channel->basicCancel($consumerTag, noreturn: true);
            }
        }

        return $messages;
    }

    public function qos(
        ?int $prefetchSize = null,
        ?int $prefetchCount = null,
        ?bool $global = null,
        ?AmqpChannel $channel = null,
    ): mixed {
        return ($channel ?? $this->channel())->basicQos(
            $prefetchSize ?? (int) data_get($this->config, 'qos.prefetch_size', 0),
            $prefetchCount ?? (int) data_get($this->config, 'qos.prefetch_count', 0),
            $global ?? (bool) data_get($this->config, 'qos.global', false),
        );
    }

    public function onReturned(callable $callback): self
    {
        $this->channel()->setReturnListener($callback);

        return $this;
    }

    public function onAck(callable $callback): self
    {
        $this->channel()->setAckHandler($callback);

        return $this;
    }

    public function onNack(callable $callback): self
    {
        $this->channel()->setNackHandler($callback);

        return $this;
    }

    public function wait(int|float|null $timeout = null): mixed
    {
        return $this->channel()->wait(timeout: $timeout);
    }

    public function config(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->config;
        }

        return data_get($this->config, $key, $default);
    }

    private function prepareChannel(string $key, AmqpChannel $channel): void
    {
        if (isset($this->preparedChannels[$key])) {
            return;
        }

        if ((bool) data_get($this->config, 'topology.auto_declare', true)) {
            $this->setupTopology($channel);
        }

        if ((bool) data_get($this->config, 'qos.enabled', true)) {
            $this->qos(channel: $channel);
        }

        if ((bool) data_get($this->config, 'publisher_confirms.enabled', true)) {
            $this->confirmSelectChannel($key, $channel);
        }

        $this->preparedChannels[$key] = true;
    }

    private function confirmSelectChannel(string $key, AmqpChannel $channel): void
    {
        if (isset($this->confirmSelectedChannels[$key])) {
            return;
        }

        $channel->confirmSelect();

        $this->confirmSelectedChannels[$key] = true;
    }

    private function channelKey(?int $channelId): string
    {
        return $channelId === null ? 'default' : (string) $channelId;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function configuredExchanges(): array
    {
        $exchanges = (array) data_get($this->config, 'topology.exchanges', []);

        if ($exchanges !== []) {
            return $this->normalizeNamedDefinitions($exchanges);
        }

        $exchange = (array) data_get($this->config, 'exchange', []);

        return (string) data_get($exchange, 'name', '') !== '' ? [$exchange] : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function configuredQueues(): array
    {
        $queues = (array) data_get($this->config, 'topology.queues', []);

        if ($queues !== []) {
            return $this->normalizeNamedDefinitions($queues);
        }

        return [(array) data_get($this->config, 'queue', [])];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function configuredBindings(): array
    {
        $bindings = (array) data_get($this->config, 'topology.bindings', []);

        if ($bindings !== []) {
            return array_values($bindings);
        }

        $queue = (string) data_get($this->config, 'queue.name', '');

        return array_map(
            static fn (array $binding): array => array_replace(['queue' => $queue], $binding),
            (array) data_get($this->config, 'queue.bindings', []),
        );
    }

    /**
     * @param array<string, mixed> $definitions
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizeNamedDefinitions(array $definitions): array
    {
        $normalized = [];

        foreach ($definitions as $key => $definition) {
            if (! is_array($definition)) {
                continue;
            }

            if (! isset($definition['name']) && is_string($key)) {
                $definition['name'] = $key;
            }

            $normalized[] = $definition;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function respondToMessage(AMQPMessage $message, mixed $result, array $options, bool $noAck): void
    {
        if ($noAck) {
            return;
        }

        if ($result instanceof ConsumerResult) {
            $result->apply($message);
            return;
        }

        if ($result === false && (bool) data_get($options, 'nack_on_false', true)) {
            $message->nack((bool) data_get($options, 'nack_on_false_requeue', false));
            return;
        }

        if ((bool) data_get($options, 'ack_on_success', true)) {
            $message->ack();
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private function respondToConsumerException(AMQPMessage $message, array $options, bool $noAck): void
    {
        if ($noAck || ! (bool) data_get($options, 'reject_on_exception', true)) {
            return;
        }

        $message->reject((bool) data_get($options, 'reject_on_exception_requeue', false));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function shouldStopOnIdle(float $lastMessageAt, array $options): bool
    {
        if ((bool) data_get($options, 'stop_when_empty', false)) {
            return true;
        }

        $idleTimeout = data_get($options, 'idle_timeout');

        return $idleTimeout !== null && microtime(true) - $lastMessageAt >= (float) $idleTimeout;
    }

    private function assertAllowedMessageSize(string $body): void
    {
        $maxSize = data_get($this->config, 'security.max_message_size');

        if ($maxSize !== null && strlen($body) > (int) $maxSize) {
            throw new SecurityException(sprintf(
                'RabbitMQ message body exceeds the configured max_message_size of %d bytes.',
                (int) $maxSize,
            ));
        }
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
