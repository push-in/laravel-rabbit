<?php

namespace Pushin\LaravelRabbit\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Queue\QueueManager;
use Pushin\LaravelRabbit\Queue\RabbitQueue;
use Throwable;

class PurgeCommand extends Command
{
    protected $signature = 'rabbitmq:purge
        {queue? : Queue name}
        {--queue-connection=rabbitmq : Laravel queue connection name}
        {--force : Purge without confirmation}';

    protected $description = 'Purge a RabbitMQ queue through the Laravel queue driver.';

    public function handle(QueueManager $queues): int
    {
        $queueName = $this->argument('queue');

        if (! $this->option('force') && ! $this->confirm(sprintf(
            'Purge RabbitMQ queue [%s]?',
            $queueName ?: '(default)',
        ))) {
            $this->warn('Purge cancelled.');

            return self::SUCCESS;
        }

        try {
            $queue = $queues->connection((string) $this->option('queue-connection'));

            if (! $queue instanceof RabbitQueue) {
                $this->error('The selected queue connection is not a rabbitmq connection.');

                return self::FAILURE;
            }

            $purged = $queue->clear($queueName);
            $this->info(sprintf('Purged %d message(s) from queue [%s].', $purged, $queueName ?: '(default)'));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Unable to purge RabbitMQ queue.');
            $this->line($exception->getMessage());

            return self::FAILURE;
        }
    }
}
