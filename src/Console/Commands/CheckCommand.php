<?php

namespace Pushin\LaravelRabbit\Console\Commands;

use Illuminate\Console\Command;
use Pushin\LaravelRabbit\RabbitManager;
use Throwable;

class CheckCommand extends Command
{
    protected $signature = 'rabbitmq:check
        {connection? : Laravel Rabbit connection name}
        {--queue= : Optionally verify that a queue exists with a passive declare}';

    protected $description = 'Check RabbitMQ connectivity and optionally verify an existing queue.';

    public function handle(RabbitManager $manager): int
    {
        $connectionName = $this->argument('connection') ?: $manager->defaultConnectionName();

        try {
            $connection = $manager->connection($connectionName);
            $channel = $connection->channel(prepare: false);

            if ($queue = $this->option('queue')) {
                $channel->queueDeclare((string) $queue, passive: true);
            }

            $this->info(sprintf('RabbitMQ connection [%s] is reachable.', $connectionName));

            if ($queue) {
                $this->info(sprintf('Queue [%s] exists.', $queue));
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error(sprintf('RabbitMQ check failed for connection [%s].', $connectionName));
            $this->line($exception->getMessage());

            return self::FAILURE;
        }
    }
}
