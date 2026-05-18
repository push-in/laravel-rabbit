<?php

namespace Pushin\LaravelRabbit\Console\Commands;

use Illuminate\Console\Command;
use Pushin\LaravelRabbit\Contracts\RabbitManagementClient;
use Throwable;

class ManagementCommand extends Command
{
    protected $signature = 'rabbitmq:management
        {--queue= : Queue name to inspect}
        {--vhost= : RabbitMQ virtual host}
        {--queues : List queues in the selected virtual host}';

    protected $description = 'Inspect RabbitMQ through the Management HTTP API.';

    public function handle(RabbitManagementClient $management): int
    {
        if (! $management->enabled()) {
            $this->error('RabbitMQ Management API is disabled. Set RABBITMQ_MANAGEMENT_ENABLED=true to use this command.');

            return self::FAILURE;
        }

        $vhost = $this->option('vhost') !== null ? (string) $this->option('vhost') : null;

        try {
            if ($this->option('queue') !== null) {
                return $this->showQueue($management, (string) $this->option('queue'), $vhost);
            }

            if ((bool) $this->option('queues')) {
                return $this->showQueues($management, $vhost);
            }

            return $this->showOverview($management);
        } catch (Throwable $exception) {
            $this->error('Unable to read RabbitMQ Management API.');
            $this->line($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function showOverview(RabbitManagementClient $management): int
    {
        $overview = $management->overview();

        if ($overview === null) {
            $this->error('RabbitMQ Management API overview endpoint returned no data.');

            return self::FAILURE;
        }

        $this->table(['Metric', 'Value'], [
            ['cluster', $this->display(data_get($overview, 'cluster_name'))],
            ['rabbitmq_version', $this->display(data_get($overview, 'rabbitmq_version'))],
            ['management_version', $this->display(data_get($overview, 'management_version'))],
            ['queues', $this->display(data_get($overview, 'object_totals.queues'))],
            ['connections', $this->display(data_get($overview, 'object_totals.connections'))],
            ['channels', $this->display(data_get($overview, 'object_totals.channels'))],
            ['consumers', $this->display(data_get($overview, 'object_totals.consumers'))],
            ['messages_ready', $this->display(data_get($overview, 'queue_totals.messages_ready'))],
            ['messages_unacknowledged', $this->display(data_get($overview, 'queue_totals.messages_unacknowledged'))],
            ['messages_total', $this->display(data_get($overview, 'queue_totals.messages'))],
        ]);

        return self::SUCCESS;
    }

    private function showQueue(RabbitManagementClient $management, string $queue, ?string $vhost): int
    {
        $stats = $management->queue($queue, $vhost);

        if ($stats === null) {
            $this->error(sprintf('Queue [%s] was not found through the RabbitMQ Management API.', $queue));

            return self::FAILURE;
        }

        $this->table(['Metric', 'Value'], [
            ['vhost', $this->display(data_get($stats, 'vhost'))],
            ['queue', $this->display(data_get($stats, 'name', $queue))],
            ['state', $this->display(data_get($stats, 'state'))],
            ['consumers', $this->display(data_get($stats, 'consumers'))],
            ['messages_ready', $this->display(data_get($stats, 'messages_ready'))],
            ['messages_unacknowledged', $this->display(data_get($stats, 'messages_unacknowledged'))],
            ['messages_total', $this->display(data_get($stats, 'messages'))],
            ['memory', $this->display(data_get($stats, 'memory'))],
            ['idle_since', $this->display(data_get($stats, 'idle_since'))],
        ]);

        return self::SUCCESS;
    }

    private function showQueues(RabbitManagementClient $management, ?string $vhost): int
    {
        $queues = $management->queues($vhost);

        $this->table(['Queue', 'Ready', 'Reserved', 'Total', 'Consumers', 'State'], array_map(
            fn (array $queue): array => [
                $this->display($queue['name'] ?? null),
                $this->display($queue['messages_ready'] ?? null),
                $this->display($queue['messages_unacknowledged'] ?? null),
                $this->display($queue['messages'] ?? null),
                $this->display($queue['consumers'] ?? null),
                $this->display($queue['state'] ?? null),
            ],
            $queues,
        ));

        return self::SUCCESS;
    }

    private function display(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'n/a';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: 'n/a';
    }
}
