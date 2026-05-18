<?php

namespace Pushin\LaravelRabbit\Console\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'rabbitmq:install {--force : Overwrite the published configuration file}';

    protected $description = 'Publish Laravel Rabbit configuration and show the required environment variables.';

    public function handle(): int
    {
        $this->callSilent('vendor:publish', [
            '--tag' => 'laravel-rabbit-config',
            '--force' => (bool) $this->option('force'),
        ]);

        $this->info('Laravel Rabbit configuration published.');
        $this->newLine();
        $this->line('Required queue setup:');
        $this->line('QUEUE_CONNECTION=rabbitmq');
        $this->newLine();
        $this->line('RabbitMQ connection example:');
        $this->line('RABBITMQ_HOST=127.0.0.1');
        $this->line('RABBITMQ_PORT=5672');
        $this->line('RABBITMQ_VHOST=/');
        $this->line('RABBITMQ_USER=guest');
        $this->line('RABBITMQ_PASSWORD=guest');
        $this->line('RABBITMQ_QUEUE=default');
        $this->newLine();
        $this->line('Optional queue payload signing:');
        $this->line('RABBITMQ_QUEUE_SIGN_PAYLOADS=true');
        $this->line('RABBITMQ_QUEUE_SIGNING_KEY="${APP_KEY}"');
        $this->line('RABBITMQ_QUEUE_INVALID_SIGNATURE_REQUEUE=false');
        $this->newLine();
        $this->line('Optional RabbitMQ Management API:');
        $this->line('RABBITMQ_MANAGEMENT_ENABLED=true');
        $this->line('RABBITMQ_MANAGEMENT_HOST=127.0.0.1');
        $this->line('RABBITMQ_MANAGEMENT_PORT=15672');
        $this->line('RABBITMQ_MANAGEMENT_USER=guest');
        $this->line('RABBITMQ_MANAGEMENT_PASSWORD=guest');

        return self::SUCCESS;
    }
}
