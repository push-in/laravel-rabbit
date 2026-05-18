<?php

namespace Pushin\LaravelRabbit\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Queue\QueueManager;
use Pushin\LaravelRabbit\Contracts\RabbitManagementClient;
use Pushin\LaravelRabbit\Queue\RabbitQueue;
use Pushin\LaravelRabbit\RabbitManager;
use Pushin\LaravelRabbit\Support\RoundTripTester;
use Pushin\LaravelRabbit\Support\SecurityPolicy;
use Throwable;

class DoctorCommand extends Command
{
    protected $signature = 'rabbitmq:doctor
        {connection? : Laravel Rabbit connection name}
        {--queue=laravel-rabbit.doctor : Queue used for the optional round-trip test}
        {--timeout=5 : Seconds to wait for the round-trip message}
        {--skip-roundtrip : Skip publish/consume round-trip test}';

    protected $description = 'Run RabbitMQ configuration, security, queue-driver, connection, and round-trip diagnostics.';

    public function handle(RabbitManager $manager, QueueManager $queues, SecurityPolicy $security, RoundTripTester $tester, RabbitManagementClient $management): int
    {
        $connectionName = $this->argument('connection') ?: $manager->defaultConnectionName();
        $failed = false;

        $this->info(sprintf('Running RabbitMQ diagnostics for connection [%s].', $connectionName));

        $connectionConfig = $manager->config("connections.{$connectionName}");

        if (! is_array($connectionConfig)) {
            $this->error(sprintf('[fail] Connection [%s] is not configured.', $connectionName));

            return self::FAILURE;
        }

        $this->info('[ok] Connection configuration found.');

        try {
            $security->assert($connectionName, $connectionConfig);
            $this->info('[ok] Security policy passed.');
        } catch (Throwable $exception) {
            $this->error('[fail] Security policy failed.');
            $this->line($exception->getMessage());
            $failed = true;
        }

        if (config('queue.default') === 'rabbitmq') {
            $this->info('[ok] QUEUE_CONNECTION is rabbitmq.');
        } else {
            $this->warn(sprintf('[warn] queue.default is [%s]. Set QUEUE_CONNECTION=rabbitmq to make queue:work use RabbitMQ by default.', config('queue.default')));
        }

        try {
            $queueConnection = $queues->connection('rabbitmq');

            if ($queueConnection instanceof RabbitQueue) {
                $this->info('[ok] Laravel queue driver [rabbitmq] is registered.');
            } else {
                $this->error('[fail] Laravel queue connection [rabbitmq] is not using the RabbitMQ driver.');
                $failed = true;
            }
        } catch (Throwable $exception) {
            $this->error('[fail] Laravel queue driver [rabbitmq] could not be resolved.');
            $this->line($exception->getMessage());
            $failed = true;
        }

        try {
            $connection = $manager->connection($connectionName);
            $connection->channel(prepare: false);
            $this->info('[ok] RabbitMQ connection opened.');
        } catch (Throwable $exception) {
            $this->error('[fail] RabbitMQ connection failed.');
            $this->line($exception->getMessage());

            return self::FAILURE;
        }

        if ($management->enabled()) {
            try {
                $result = $management->alivenessTest((string) data_get($connectionConfig, 'vhost', '/'));

                if ($result === null) {
                    throw new \RuntimeException('The aliveness endpoint returned no data.');
                }

                $this->info('[ok] RabbitMQ Management API is reachable.');
            } catch (Throwable $exception) {
                $this->error('[fail] RabbitMQ Management API check failed.');
                $this->line($exception->getMessage());
                $failed = true;
            }
        }

        if (! $this->option('skip-roundtrip')) {
            $result = $tester->run($manager->connection($connectionName), (string) $this->option('queue'), [
                'timeout' => (float) $this->option('timeout'),
                'declare' => true,
                'durable' => false,
                'auto_delete' => true,
            ]);

            if ($result->successful) {
                $this->info('[ok] Publish/consume round-trip passed.');
            } else {
                $this->error('[fail] Publish/consume round-trip failed.');
                $this->line($result->message);
                $failed = true;
            }
        }

        if ($failed) {
            $this->error('RabbitMQ diagnostics finished with failures.');

            return self::FAILURE;
        }

        $this->info('RabbitMQ diagnostics passed.');

        return self::SUCCESS;
    }
}
