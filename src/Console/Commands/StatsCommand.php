<?php

namespace Pushin\LaravelRabbit\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Queue\QueueManager;
use Pushin\LaravelRabbit\Queue\RabbitQueue;
use Throwable;

class StatsCommand extends Command
{
    protected $signature = 'rabbitmq:stats
        {queue? : Queue name}
        {--queue-connection=rabbitmq : Laravel queue connection name}';

    protected $description = 'Show RabbitMQ queue statistics available through the Laravel queue driver.';

    public function handle(QueueManager $queues): int
    {
        $queueName = $this->argument('queue');

        try {
            $queue = $queues->connection((string) $this->option('queue-connection'));

            if (! $queue instanceof RabbitQueue) {
                $this->error('The selected queue connection is not a rabbitmq connection.');

                return self::FAILURE;
            }

            $managementState = 'disabled';

            if ($queue->managementApiEnabled()) {
                $managementState = $queue->managementQueue($queueName) === null
                    ? 'enabled (unavailable for this queue)'
                    : 'enabled';
            }

            $this->table(['Metric', 'Value'], [
                ['queue', $queueName ?: '(default)'],
                ['management_api', $managementState],
                ['pending', $queue->pendingSize($queueName)],
                ['delayed', $queue->delayedSize($queueName)],
                ['reserved', $queue->reservedSize($queueName)],
                ['total', $queue->size($queueName)],
            ]);

            if (! $queue->managementApiEnabled()) {
                $this->warn('Enable the RabbitMQ Management API integration for real reserved and delayed queue metrics.');
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Unable to read RabbitMQ queue statistics.');
            $this->line($exception->getMessage());

            return self::FAILURE;
        }
    }
}
