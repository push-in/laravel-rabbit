<?php

namespace Pushin\LaravelRabbit\ValueObjects;

use PhpAmqpLib\Message\AMQPMessage;

final class ConsumerResult
{
    private const ACK = 'ack';
    private const NACK = 'nack';
    private const REJECT = 'reject';
    private const SKIP = 'skip';

    private function __construct(
        private readonly string $action,
        private readonly bool $requeue = false,
        private readonly bool $multiple = false,
    ) {
    }

    public static function ack(bool $multiple = false): self
    {
        return new self(self::ACK, multiple: $multiple);
    }

    public static function nack(bool $requeue = false, bool $multiple = false): self
    {
        return new self(self::NACK, $requeue, $multiple);
    }

    public static function reject(bool $requeue = false): self
    {
        return new self(self::REJECT, $requeue);
    }

    public static function skip(): self
    {
        return new self(self::SKIP);
    }

    public function apply(AMQPMessage $message): void
    {
        match ($this->action) {
            self::ACK => $message->ack($this->multiple),
            self::NACK => $message->nack($this->requeue, $this->multiple),
            self::REJECT => $message->reject($this->requeue),
            self::SKIP => null,
        };
    }
}
