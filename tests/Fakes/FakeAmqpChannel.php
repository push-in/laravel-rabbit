<?php

namespace Pushin\LaravelRabbit\Tests\Fakes;

use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use Pushin\LaravelRabbit\Contracts\AmqpChannel;

class FakeAmqpChannel implements AmqpChannel
{
    /** @var array<int, int|null> */
    public array $channelIds = [];

    /** @var array<int, array<string, mixed>> */
    public array $exchangeDeclarations = [];

    /** @var array<int, array<string, mixed>> */
    public array $queueDeclarations = [];

    /** @var array<int, array<string, mixed>> */
    public array $bindings = [];

    /** @var array<int, array<string, mixed>> */
    public array $purges = [];

    /** @var array<int, array<string, mixed>> */
    public array $published = [];

    /** @var array<int, array<string, mixed>> */
    public array $qosCalls = [];

    /** @var array<int, int|float> */
    public array $ackWaits = [];

    /** @var array<int, int|float> */
    public array $ackReturnWaits = [];

    /** @var array<int, bool> */
    public array $confirmSelects = [];

    /** @var array<int, array<string, mixed>> */
    public array $consumes = [];

    /** @var array<int, array<string, mixed>> */
    public array $cancels = [];

    /** @var array<int, AMQPMessage> */
    public array $queuedMessages = [];

    public ?AMQPMessage $getMessage = null;

    public bool $loopbackPublishes = false;

    public bool $consuming = false;

    private $consumerCallback = null;

    public function native(): mixed
    {
        return null;
    }

    public function close(): void
    {
        $this->consuming = false;
    }

    public function hasCallbacks(): bool
    {
        return $this->consuming;
    }

    public function wait(?array $allowedMethods = null, bool $nonBlocking = false, int|float|null $timeout = null): mixed
    {
        if ($this->queuedMessages === [] || ! is_callable($this->consumerCallback)) {
            throw new AMQPTimeoutException('No fake message available.', $timeout);
        }

        ($this->consumerCallback)(array_shift($this->queuedMessages));

        return null;
    }

    public function exchangeDeclare(
        string $exchange,
        string $type = 'direct',
        bool $passive = false,
        bool $durable = true,
        bool $autoDelete = false,
        bool $internal = false,
        bool $nowait = false,
        array $arguments = [],
        ?int $ticket = null,
    ): mixed {
        $this->exchangeDeclarations[] = compact(
            'exchange',
            'type',
            'passive',
            'durable',
            'autoDelete',
            'internal',
            'nowait',
            'arguments',
            'ticket',
        );

        return null;
    }

    public function queueDeclare(
        string $queue = '',
        bool $passive = false,
        bool $durable = true,
        bool $exclusive = false,
        bool $autoDelete = false,
        bool $nowait = false,
        array $arguments = [],
        ?int $ticket = null,
    ): ?array {
        $this->queueDeclarations[] = compact(
            'queue',
            'passive',
            'durable',
            'exclusive',
            'autoDelete',
            'nowait',
            'arguments',
            'ticket',
        );

        return [$queue, 0, 0];
    }

    public function queueBind(
        string $queue,
        string $exchange,
        string $routingKey = '',
        bool $nowait = false,
        array $arguments = [],
        ?int $ticket = null,
    ): mixed {
        $this->bindings[] = compact('queue', 'exchange', 'routingKey', 'nowait', 'arguments', 'ticket');

        return null;
    }

    public function queuePurge(string $queue = '', bool $nowait = false, ?int $ticket = null): int|string|null
    {
        $this->purges[] = compact('queue', 'nowait', 'ticket');

        return count($this->queuedMessages);
    }

    public function basicPublish(
        AMQPMessage $message,
        string $exchange = '',
        string $routingKey = '',
        bool $mandatory = false,
        bool $immediate = false,
        ?int $ticket = null,
    ): void {
        $this->published[] = compact('message', 'exchange', 'routingKey', 'mandatory', 'immediate', 'ticket');

        if ($this->loopbackPublishes) {
            $this->getMessage = new FakeDeliveredMessage($message->getBody(), $message->get_properties());
        }
    }

    public function basicGet(string $queue = '', bool $noAck = false, ?int $ticket = null): ?AMQPMessage
    {
        $message = $this->getMessage;
        $this->getMessage = null;

        return $message;
    }

    public function basicConsume(
        string $queue = '',
        string $consumerTag = '',
        bool $noLocal = false,
        bool $noAck = false,
        bool $exclusive = false,
        bool $nowait = false,
        ?callable $callback = null,
        ?int $ticket = null,
        array $arguments = [],
    ): string {
        $consumerTag = $consumerTag !== '' ? $consumerTag : 'fake-consumer';
        $this->consumerCallback = $callback;
        $this->consuming = true;
        $this->consumes[] = compact(
            'queue',
            'consumerTag',
            'noLocal',
            'noAck',
            'exclusive',
            'nowait',
            'ticket',
            'arguments',
        );

        return $consumerTag;
    }

    public function basicCancel(string $consumerTag, bool $nowait = false, bool $noreturn = false): mixed
    {
        $this->cancels[] = compact('consumerTag', 'nowait', 'noreturn');
        $this->consuming = false;

        return $consumerTag;
    }

    public function basicQos(int $prefetchSize = 0, int $prefetchCount = 0, bool $global = false): mixed
    {
        $this->qosCalls[] = compact('prefetchSize', 'prefetchCount', 'global');

        return null;
    }

    public function confirmSelect(bool $nowait = false): mixed
    {
        $this->confirmSelects[] = $nowait;

        return null;
    }

    public function waitForPendingAcks(int|float $timeout = 0): void
    {
        $this->ackWaits[] = $timeout;
    }

    public function waitForPendingAcksReturns(int|float $timeout = 0): void
    {
        $this->ackReturnWaits[] = $timeout;
    }

    public function setReturnListener(callable $callback): void
    {
    }

    public function setAckHandler(callable $callback): void
    {
    }

    public function setNackHandler(callable $callback): void
    {
    }
}
