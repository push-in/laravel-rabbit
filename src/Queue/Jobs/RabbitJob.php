<?php

namespace Pushin\LaravelRabbit\Queue\Jobs;

use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Jobs\Job;
use OutOfBoundsException;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Pushin\LaravelRabbit\Queue\RabbitQueue;

class RabbitJob extends Job implements JobContract
{
    public function __construct(
        Container $container,
        private readonly RabbitQueue $rabbit,
        private readonly AMQPMessage $message,
        string $connectionName,
        string $queue,
    ) {
        $this->container = $container;
        $this->connectionName = $connectionName;
        $this->queue = $queue;
    }

    public function delete(): void
    {
        parent::delete();

        $this->message->ack();
    }

    public function release($delay = 0): void
    {
        parent::release($delay);

        $this->rabbit->releaseRaw($this->getRawBody(), $this->queue, $this->attempts(), (int) $delay);
        $this->message->ack();
    }

    public function attempts(): int
    {
        return max(1, (int) $this->header('laravel-attempts', 0) + 1);
    }

    public function getJobId(): string|int|null
    {
        try {
            return $this->message->get('message_id');
        } catch (OutOfBoundsException) {
            return $this->payload()['uuid'] ?? $this->message->getDeliveryTag();
        }
    }

    public function getRawBody(): string
    {
        return $this->message->getBody();
    }

    public function getRabbitMessage(): AMQPMessage
    {
        return $this->message;
    }

    private function header(string $key, mixed $default = null): mixed
    {
        try {
            $headers = $this->message->get('application_headers');
        } catch (OutOfBoundsException) {
            return $default;
        }

        if ($headers instanceof AMQPTable) {
            $headers = $headers->getNativeData();
        }

        return is_array($headers) ? ($headers[$key] ?? $default) : $default;
    }
}
