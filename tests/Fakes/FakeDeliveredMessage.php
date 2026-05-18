<?php

namespace Pushin\LaravelRabbit\Tests\Fakes;

use PhpAmqpLib\Message\AMQPMessage;

class FakeDeliveredMessage extends AMQPMessage
{
    public bool $acked = false;

    public bool $nacked = false;

    public bool $requeued = false;

    public function ack($multiple = false): void
    {
        $this->acked = true;
    }

    public function nack($requeue = false, $multiple = false): void
    {
        $this->nacked = true;
        $this->requeued = $requeue;
    }
}
