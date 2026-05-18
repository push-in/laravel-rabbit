<?php

namespace Pushin\LaravelRabbit\Queue;

use Illuminate\Contracts\Queue\ClearableQueue;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;
use Illuminate\Support\Str;
use OutOfBoundsException;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Pushin\LaravelRabbit\Contracts\AmqpChannel;
use Pushin\LaravelRabbit\Contracts\RabbitManagementClient;
use Pushin\LaravelRabbit\Exceptions\ConfigurationException;
use Pushin\LaravelRabbit\Management\NullRabbitManagementClient;
use Pushin\LaravelRabbit\Queue\Jobs\RabbitJob;
use Pushin\LaravelRabbit\RabbitConnection;
use Pushin\LaravelRabbit\RabbitManager;
use Throwable;

class RabbitQueue extends Queue implements QueueContract, ClearableQueue
{
    private readonly string $default;

    private readonly string $rabbitConnectionName;

    private const SIGNATURE_HEADER = 'laravel-signature';

    private const SIGNATURE_ALGORITHM_HEADER = 'laravel-signature-algorithm';

    private const SIGNATURE_ALGORITHM = 'hmac-sha256';

    /** @var array<string, bool> */
    private array $declaredQueues = [];

    /** @var array<string, bool> */
    private array $declaredDelayQueues = [];

    /** @var array<string, array<int|string, mixed>|null> */
    private array $managementQueueCache = [];

    /** @var array<string, array<int, array<int|string, mixed>>|null> */
    private array $managementQueuesCache = [];

    private readonly RabbitManagementClient $management;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly RabbitManager $manager,
        array $config,
        ?RabbitManagementClient $management = null,
    ) {
        $this->config = $config;
        $this->management = $management ?? new NullRabbitManagementClient();
        $this->default = (string) data_get($config, 'queue', 'default');
        $this->rabbitConnectionName = (string) data_get($config, 'rabbit_connection', data_get($config, 'connection', 'default'));
        $this->dispatchAfterCommit = (bool) data_get($config, 'after_commit', false);
    }

    public function size($queue = null): int
    {
        $queue = $this->getQueue($queue);

        $stats = $this->managementQueueStats($queue);

        if ($stats !== null && isset($stats['messages'])) {
            return (int) $stats['messages'];
        }

        $this->ensureQueue($queue);

        $declaration = $this->channel()->queueDeclare(
            $queue,
            false,
            (bool) data_get($this->config, 'durable', true),
            (bool) data_get($this->config, 'exclusive', false),
            (bool) data_get($this->config, 'auto_delete', false),
            false,
            (array) data_get($this->config, 'queue_arguments', []),
        );

        return (int) ($declaration[1] ?? 0);
    }

    public function pendingSize($queue = null): int
    {
        $queue = $this->getQueue($queue);
        $stats = $this->managementQueueStats($queue);

        if ($stats !== null && isset($stats['messages_ready'])) {
            return (int) $stats['messages_ready'];
        }

        return $this->size($queue);
    }

    public function delayedSize($queue = null): int
    {
        $queue = $this->getQueue($queue);

        if ((string) data_get($this->config, 'delay.strategy', 'ttl') !== 'ttl') {
            return 0;
        }

        $queues = $this->managementQueuesStats();

        if ($queues === null) {
            return 0;
        }

        $prefix = $this->delayQueuePrefixFor($queue);
        $messages = 0;

        foreach ($queues as $stats) {
            $name = (string) ($stats['name'] ?? '');

            if ($name !== '' && str_starts_with($name, $prefix)) {
                $messages += (int) ($stats['messages'] ?? 0);
            }
        }

        return $messages;
    }

    public function reservedSize($queue = null): int
    {
        $stats = $this->managementQueueStats($this->getQueue($queue));

        return $stats === null ? 0 : (int) ($stats['messages_unacknowledged'] ?? 0);
    }

    public function creationTimeOfOldestPendingJob($queue = null): ?int
    {
        return null;
    }

    public function push($job, $data = '', $queue = null): mixed
    {
        return $this->enqueueUsing(
            $job,
            $this->createPayload($job, $this->getQueue($queue), $data),
            $queue,
            null,
            fn (string $payload, ?string $queue): mixed => $this->pushRaw($payload, $queue),
        );
    }

    public function pushRaw($payload, $queue = null, array $options = []): mixed
    {
        $queue = $this->getQueue($queue);
        $this->ensureQueue($queue);

        $messageId = $this->messageId($payload);

        $this->rabbit()->publish(
            $payload,
            $this->routingKey($queue),
            $this->exchange(),
            $this->messageProperties($payload, $queue, $options, $messageId),
            [
                'mandatory' => (bool) data_get($this->config, 'mandatory', false),
                'wait_for_returns' => (bool) data_get($this->config, 'wait_for_returns', false),
                'prepare_channel' => false,
                'confirm_select' => true,
            ],
        );

        return $messageId;
    }

    public function later($delay, $job, $data = '', $queue = null): mixed
    {
        return $this->enqueueUsing(
            $job,
            $this->createPayload($job, $this->getQueue($queue), $data, $delay),
            $queue,
            $delay,
            fn (string $payload, ?string $queue, mixed $delay): mixed => $this->laterRaw(
                $this->secondsUntil($delay),
                $payload,
                $queue,
            ),
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    public function laterRaw(int $delay, string $payload, ?string $queue = null, array $options = []): mixed
    {
        if ($delay <= 0) {
            return $this->pushRaw($payload, $queue, $options);
        }

        return match ((string) data_get($this->config, 'delay.strategy', 'ttl')) {
            'x-delayed-message' => $this->laterUsingDelayedExchange($delay, $payload, $this->getQueue($queue), $options),
            'none' => $this->pushRaw($payload, $queue, $options),
            default => $this->laterUsingTtlQueue($delay, $payload, $this->getQueue($queue), $options),
        };
    }

    public function releaseRaw(string $payload, string $queue, int $attempts, int $delay = 0): mixed
    {
        return $this->laterRaw($delay, $payload, $queue, ['attempts' => $attempts]);
    }

    public function bulk($jobs, $data = '', $queue = null): void
    {
        foreach ((array) $jobs as $job) {
            if (isset($job->delay)) {
                $this->later($job->delay, $job, $data, $queue);
            } else {
                $this->push($job, $data, $queue);
            }
        }
    }

    public function pop($queue = null): ?RabbitJob
    {
        $queue = $this->getQueue($queue);
        $this->ensureQueue($queue);
        $message = null;
        $startedAt = microtime(true);
        $blockFor = (float) data_get($this->config, 'block_for', 0);

        do {
            $message = $this->channel()->basicGet($queue, false);

            if ($message instanceof AMQPMessage || $blockFor <= 0) {
                break;
            }

            usleep(100000);
        } while (microtime(true) - $startedAt < $blockFor);

        if (! $message instanceof AMQPMessage) {
            return null;
        }

        if (! $this->messageSignatureIsValid($message, $queue)) {
            $message->nack((bool) data_get($this->config, 'security.invalid_signature_requeue', false));

            return null;
        }

        return new RabbitJob($this->container, $this, $message, $this->connectionName, $queue);
    }

    public function clear($queue): int
    {
        $queue = $this->getQueue($queue);
        $this->ensureQueue($queue);

        return (int) $this->channel()->queuePurge($queue);
    }

    public function getQueue($queue): string
    {
        return $queue ?: $this->default;
    }

    public function getRabbitConnection(): RabbitConnection
    {
        return $this->rabbit();
    }

    public function managementApiEnabled(): bool
    {
        return $this->management->enabled();
    }

    /**
     * @return array<int|string, mixed>|null
     */
    public function managementQueue($queue = null): ?array
    {
        return $this->managementQueueStats($this->getQueue($queue));
    }

    /**
     * @return array<int, array<int|string, mixed>>|null
     */
    public function managementQueues(): ?array
    {
        return $this->managementQueuesStats();
    }

    private function rabbit(): RabbitConnection
    {
        return $this->manager->connection($this->rabbitConnectionName);
    }

    private function channel(): AmqpChannel
    {
        return $this->rabbit()->channel(prepare: false);
    }

    private function ensureQueue(string $queue): void
    {
        if (! (bool) data_get($this->config, 'declare', true) || isset($this->declaredQueues[$queue])) {
            return;
        }

        $channel = $this->channel();
        $exchange = $this->exchange();

        if ($exchange !== '') {
            $channel->exchangeDeclare(
                $exchange,
                (string) data_get($this->config, 'exchange_type', 'direct'),
                false,
                (bool) data_get($this->config, 'exchange_durable', true),
                (bool) data_get($this->config, 'exchange_auto_delete', false),
                false,
                false,
                (array) data_get($this->config, 'exchange_arguments', []),
            );
        }

        $channel->queueDeclare(
            $queue,
            false,
            (bool) data_get($this->config, 'durable', true),
            (bool) data_get($this->config, 'exclusive', false),
            (bool) data_get($this->config, 'auto_delete', false),
            false,
            (array) data_get($this->config, 'queue_arguments', []),
        );

        if ($exchange !== '') {
            $channel->queueBind($queue, $exchange, $this->routingKey($queue));
        }

        $this->declaredQueues[$queue] = true;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function laterUsingTtlQueue(int $delay, string $payload, string $queue, array $options): mixed
    {
        $this->ensureQueue($queue);

        $delayQueue = $this->delayQueueName($queue, $delay);

        if (! isset($this->declaredDelayQueues[$delayQueue])) {
            $this->channel()->queueDeclare(
                $delayQueue,
                false,
                true,
                false,
                (bool) data_get($this->config, 'delay.auto_delete', false),
                false,
                array_replace([
                    'x-message-ttl' => $delay * 1000,
                    'x-dead-letter-exchange' => $this->exchange(),
                    'x-dead-letter-routing-key' => $this->routingKey($queue),
                ], (array) data_get($this->config, 'delay.queue_arguments', [])),
            );

            $this->declaredDelayQueues[$delayQueue] = true;
        }

        $messageId = $this->messageId($payload);

        $this->rabbit()->publish(
            $payload,
            $delayQueue,
            '',
            $this->messageProperties($payload, $queue, $options, $messageId),
            [
                'prepare_channel' => false,
                'confirm_select' => true,
            ],
        );

        return $messageId;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function laterUsingDelayedExchange(int $delay, string $payload, string $queue, array $options): mixed
    {
        $this->ensureQueue($queue);

        $exchange = (string) data_get($this->config, 'delay.exchange', $this->exchange() !== '' ? $this->exchange() . '.delayed' : 'laravel.delayed');
        $routingKey = $this->routingKey($queue);

        $this->channel()->exchangeDeclare(
            $exchange,
            'x-delayed-message',
            false,
            true,
            false,
            false,
            false,
            ['x-delayed-type' => (string) data_get($this->config, 'exchange_type', 'direct')],
        );

        $this->channel()->queueBind($queue, $exchange, $routingKey);

        $messageId = $this->messageId($payload);
        $properties = $this->messageProperties($payload, $queue, $options, $messageId);
        $properties['headers']['x-delay'] = $delay * 1000;

        $this->rabbit()->publish($payload, $routingKey, $exchange, $properties, [
            'prepare_channel' => false,
            'confirm_select' => true,
        ]);

        return $messageId;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function messageProperties(string $payload, string $queue, array $options, string $messageId): array
    {
        $headers = array_replace(
            (array) data_get($this->config, 'headers', []),
            [
                'laravel-queue' => $queue,
                'laravel-attempts' => (int) ($options['attempts'] ?? 0),
            ],
        );

        if ($this->shouldSignPayloads()) {
            $headers[self::SIGNATURE_HEADER] = $this->payloadSignature($payload, $queue);
            $headers[self::SIGNATURE_ALGORITHM_HEADER] = self::SIGNATURE_ALGORITHM;
        }

        return [
            'content_type' => 'application/json',
            'delivery_mode' => (int) data_get($this->config, 'delivery_mode', 2),
            'message_id' => $messageId,
            'timestamp' => time(),
            'app_id' => (string) data_get($this->config, 'app_id', 'laravel'),
            'headers' => $headers,
        ];
    }

    private function exchange(): string
    {
        return (string) data_get($this->config, 'exchange', '');
    }

    private function routingKey(string $queue): string
    {
        $routingKey = data_get($this->config, 'routing_key');

        return $routingKey === null ? $queue : (string) $routingKey;
    }

    private function delayQueueName(string $queue, int $delay): string
    {
        return sprintf(
            '%s%s.%d',
            (string) data_get($this->config, 'delay.queue_prefix', 'laravel.delay.'),
            str_replace(['/', '\\'], '.', $queue),
            $delay,
        );
    }

    private function delayQueuePrefixFor(string $queue): string
    {
        return sprintf(
            '%s%s.',
            (string) data_get($this->config, 'delay.queue_prefix', 'laravel.delay.'),
            str_replace(['/', '\\'], '.', $queue),
        );
    }

    /**
     * @return array<int|string, mixed>|null
     */
    private function managementQueueStats(string $queue): ?array
    {
        if (! $this->management->enabled()) {
            return null;
        }

        $vhost = $this->rabbitVhost();
        $key = $vhost . "\0" . $queue;

        if (array_key_exists($key, $this->managementQueueCache)) {
            return $this->managementQueueCache[$key];
        }

        try {
            return $this->managementQueueCache[$key] = $this->management->queue($queue, $vhost);
        } catch (Throwable) {
            return $this->managementQueueCache[$key] = null;
        }
    }

    /**
     * @return array<int, array<int|string, mixed>>|null
     */
    private function managementQueuesStats(): ?array
    {
        if (! $this->management->enabled()) {
            return null;
        }

        $vhost = $this->rabbitVhost();

        if (array_key_exists($vhost, $this->managementQueuesCache)) {
            return $this->managementQueuesCache[$vhost];
        }

        try {
            return $this->managementQueuesCache[$vhost] = $this->management->queues($vhost);
        } catch (Throwable) {
            return $this->managementQueuesCache[$vhost] = null;
        }
    }

    private function rabbitVhost(): string
    {
        return (string) $this->manager->config(
            "connections.{$this->rabbitConnectionName}.vhost",
            $this->manager->config('management.vhost', '/'),
        );
    }

    private function shouldSignPayloads(): bool
    {
        return (bool) data_get($this->config, 'security.sign_payloads', false);
    }

    private function shouldVerifyPayloadSignatures(): bool
    {
        return (bool) data_get($this->config, 'security.verify_payload_signatures', $this->shouldSignPayloads());
    }

    private function messageSignatureIsValid(AMQPMessage $message, string $queue): bool
    {
        if (! $this->shouldVerifyPayloadSignatures()) {
            return true;
        }

        $headers = $this->messageHeaders($message);
        $signature = $headers[self::SIGNATURE_HEADER] ?? null;
        $algorithm = $headers[self::SIGNATURE_ALGORITHM_HEADER] ?? self::SIGNATURE_ALGORITHM;

        if (! is_string($signature) || ! is_string($algorithm) || $algorithm !== self::SIGNATURE_ALGORITHM) {
            return false;
        }

        return hash_equals($this->payloadSignature($message->getBody(), $queue), $signature);
    }

    /**
     * @return array<string, mixed>
     */
    private function messageHeaders(AMQPMessage $message): array
    {
        try {
            $headers = $message->get('application_headers');
        } catch (OutOfBoundsException) {
            return [];
        }

        if ($headers instanceof AMQPTable) {
            $headers = $headers->getNativeData();
        }

        return is_array($headers) ? $headers : [];
    }

    private function payloadSignature(string $payload, string $queue): string
    {
        return hash_hmac('sha256', $queue . "\n" . $payload, $this->payloadSigningKey());
    }

    private function payloadSigningKey(): string
    {
        $key = (string) data_get($this->config, 'security.signing_key', '');

        if ($key === '') {
            throw new ConfigurationException('RabbitMQ queue payload signing requires RABBITMQ_QUEUE_SIGNING_KEY or APP_KEY.');
        }

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

            if ($decoded !== false) {
                return $decoded;
            }
        }

        return $key;
    }

    private function messageId(string $payload): string
    {
        $decoded = json_decode($payload, true);

        return is_array($decoded) && isset($decoded['uuid'])
            ? (string) $decoded['uuid']
            : (string) Str::uuid();
    }
}
