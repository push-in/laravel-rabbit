<?php

namespace Pushin\LaravelRabbit\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Queue\QueueManager;
use Pushin\LaravelRabbit\Queue\RabbitQueue;
use Pushin\LaravelRabbit\RabbitManager;
use Throwable;

class SetupCommand extends Command
{
    protected $signature = 'rabbitmq:setup
        {connection? : Laravel Rabbit connection name}
        {--queue= : Also declare a Laravel queue-driver queue}
        {--queue-connection=rabbitmq : Laravel queue connection used when --queue is provided}';

    protected $description = 'Declare configured RabbitMQ topology and optional Laravel queue-driver queues.';

    public function handle(RabbitManager $manager, QueueManager $queues): int
    {
        $connectionName = $this->argument('connection') ?: $manager->defaultConnectionName();

        try {
            $manager->connection($connectionName)->setupTopology();
            $this->info(sprintf('RabbitMQ topology for connection [%s] is ready.', $connectionName));

            if ($queue = $this->option('queue')) {
                $queueConnection = $queues->connection((string) $this->option('queue-connection'));

                if (! $queueConnection instanceof RabbitQueue) {
                    $this->error('The selected queue connection is not a rabbitmq connection.');

                    return self::FAILURE;
                }

                $queueConnection->size((string) $queue);
                $this->info(sprintf('Laravel queue [%s] is ready.', $queue));
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error(sprintf('RabbitMQ setup failed for connection [%s].', $connectionName));
            $this->line($exception->getMessage());

            return self::FAILURE;
        }
    }
}
