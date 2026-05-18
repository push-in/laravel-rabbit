<?php

namespace Pushin\LaravelRabbit\Connections;

use PhpAmqpLib\Connection\AMQPConnectionFactory as NativeConnectionFactory;
use Pushin\LaravelRabbit\Contracts\AmqpConnection;
use Pushin\LaravelRabbit\Contracts\AmqpConnectionFactory;
use Pushin\LaravelRabbit\Exceptions\ConnectionException;
use RuntimeException;
use Throwable;

class PhpAmqpLibConnectionFactory implements AmqpConnectionFactory
{
    public function __construct(private readonly ConnectionConfigFactory $configFactory)
    {
    }

    public function connect(string $name, array $config): AmqpConnection
    {
        $attempts = max(1, (int) data_get($config, 'reconnect.attempts', 1));
        $sleepMs = max(0, (int) data_get($config, 'reconnect.sleep_ms', 250));
        $multiplier = max(1.0, (float) data_get($config, 'reconnect.multiplier', 1.5));
        $lastException = null;
        $connectionConfigs = $this->configFactory->makeAll($name, $config);

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            foreach ($connectionConfigs as $connectionConfig) {
                try {
                    return new PhpAmqpLibConnection(NativeConnectionFactory::create($connectionConfig));
                } catch (Throwable $exception) {
                    $lastException = $exception;
                }
            }

            if ($attempt < $attempts && $sleepMs > 0) {
                usleep($sleepMs * 1000);
                $sleepMs = (int) round($sleepMs * $multiplier);
            }
        }

        throw ConnectionException::unableToConnect(
            $name,
            $lastException ?? new RuntimeException('No RabbitMQ host configuration was available.'),
        );
    }
}
