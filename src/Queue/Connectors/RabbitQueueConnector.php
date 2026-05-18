<?php

namespace Pushin\LaravelRabbit\Queue\Connectors;

use Illuminate\Queue\Connectors\ConnectorInterface;
use Pushin\LaravelRabbit\Contracts\RabbitManagementClient;
use Pushin\LaravelRabbit\Queue\RabbitQueue;
use Pushin\LaravelRabbit\RabbitManager;

class RabbitQueueConnector implements ConnectorInterface
{
    public function __construct(
        private readonly RabbitManager $manager,
        private readonly RabbitManagementClient $management,
    )
    {
    }

    /**
     * @param array<string, mixed> $config
     */
    public function connect(array $config): RabbitQueue
    {
        return new RabbitQueue($this->manager, $config, $this->management);
    }
}
