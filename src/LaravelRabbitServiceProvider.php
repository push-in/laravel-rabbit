<?php

namespace Pushin\LaravelRabbit;

use Illuminate\Support\ServiceProvider;
use Pushin\LaravelRabbit\Console\Commands\CheckCommand;
use Pushin\LaravelRabbit\Console\Commands\ConsumeTestCommand;
use Pushin\LaravelRabbit\Console\Commands\DoctorCommand;
use Pushin\LaravelRabbit\Console\Commands\InstallCommand;
use Pushin\LaravelRabbit\Console\Commands\ManagementCommand;
use Pushin\LaravelRabbit\Console\Commands\PurgeCommand;
use Pushin\LaravelRabbit\Console\Commands\SetupCommand;
use Pushin\LaravelRabbit\Console\Commands\StatsCommand;
use Pushin\LaravelRabbit\Connections\ConnectionConfigFactory;
use Pushin\LaravelRabbit\Connections\PhpAmqpLibConnectionFactory;
use Pushin\LaravelRabbit\Contracts\AmqpConnectionFactory;
use Pushin\LaravelRabbit\Contracts\RabbitManagementClient;
use Pushin\LaravelRabbit\Management\HttpRabbitManagementClient;
use Pushin\LaravelRabbit\Queue\Connectors\RabbitQueueConnector;
use Pushin\LaravelRabbit\Support\SecurityPolicy;

class LaravelRabbitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/laravel-rabbit.php', 'laravel-rabbit');
        $this->mergeLaravelQueueConnection();

        $this->app->singleton(SecurityPolicy::class);
        $this->app->singleton(ConnectionConfigFactory::class);
        $this->app->singleton(AmqpConnectionFactory::class, PhpAmqpLibConnectionFactory::class);
        $this->app->singleton(RabbitManagementClient::class, function (): RabbitManagementClient {
            return new HttpRabbitManagementClient(config('laravel-rabbit.management', []));
        });

        $this->app->singleton(RabbitManager::class, function (): RabbitManager {
            return new RabbitManager(
                config('laravel-rabbit', []),
                $this->app->make(AmqpConnectionFactory::class),
            );
        });

        $this->app->singleton(LaravelRabbit::class, function (): LaravelRabbit {
            return new LaravelRabbit(
                $this->app->make(RabbitManager::class),
                config('laravel-rabbit', []),
                $this->app->make(RabbitManagementClient::class),
            );
        });

        $this->app->alias(LaravelRabbit::class, 'laravel-rabbit');
        $this->app->alias(RabbitManager::class, 'laravel-rabbit.manager');
        $this->app->alias(RabbitManagementClient::class, 'laravel-rabbit.management');

        $this->registerLaravelQueueConnector();
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../config/laravel-rabbit.php' => config_path('laravel-rabbit.php'),
        ], 'laravel-rabbit-config');

        $this->commands([
            CheckCommand::class,
            ConsumeTestCommand::class,
            DoctorCommand::class,
            InstallCommand::class,
            ManagementCommand::class,
            PurgeCommand::class,
            SetupCommand::class,
            StatsCommand::class,
        ]);
    }

    private function mergeLaravelQueueConnection(): void
    {
        $config = $this->app['config'];
        $defaults = $config->get('laravel-rabbit.laravel_queue', []);
        $current = $config->get('queue.connections.rabbitmq');

        $config->set(
            'queue.connections.rabbitmq',
            is_array($current) ? array_replace_recursive($defaults, $current) : $defaults,
        );
    }

    private function registerLaravelQueueConnector(): void
    {
        $resolver = function ($manager): void {
            $manager->addConnector('rabbitmq', function (): RabbitQueueConnector {
                return new RabbitQueueConnector(
                    $this->app->make(RabbitManager::class),
                    $this->app->make(RabbitManagementClient::class),
                );
            });
        };

        $this->app->afterResolving('queue', $resolver);

        if ($this->app->bound('queue') && $this->app->resolved('queue')) {
            $resolver($this->app->make('queue'));
        }
    }
}
