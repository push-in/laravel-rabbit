<?php

namespace Pushin\LaravelRabbit\Console\Commands;

use Illuminate\Console\Command;
use Pushin\LaravelRabbit\RabbitManager;
use Pushin\LaravelRabbit\Support\RoundTripTester;
use Throwable;

class ConsumeTestCommand extends Command
{
    protected $signature = 'rabbitmq:consume-test
        {connection? : Laravel Rabbit connection name}
        {--queue=laravel-rabbit.healthcheck : Queue used for the round-trip test}
        {--timeout=5 : Seconds to wait for the test message}
        {--no-declare : Do not declare the test queue before publishing}
        {--durable : Declare the test queue as durable}
        {--keep-queue : Do not auto-delete the declared test queue}';

    protected $description = 'Publish and consume a test message through RabbitMQ.';

    public function handle(RabbitManager $manager, RoundTripTester $tester): int
    {
        $connectionName = $this->argument('connection') ?: $manager->defaultConnectionName();
        $queue = (string) $this->option('queue');

        try {
            $result = $tester->run($manager->connection($connectionName), $queue, [
                'timeout' => (float) $this->option('timeout'),
                'declare' => ! (bool) $this->option('no-declare'),
                'durable' => (bool) $this->option('durable'),
                'auto_delete' => ! (bool) $this->option('keep-queue'),
            ]);

            if (! $result->successful) {
                $this->error($result->message);

                return self::FAILURE;
            }

            $this->info(sprintf('RabbitMQ consume test passed on queue [%s].', $queue));
            $this->line($result->message);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error(sprintf('RabbitMQ consume test failed for connection [%s].', $connectionName));
            $this->line($exception->getMessage());

            return self::FAILURE;
        }
    }
}
