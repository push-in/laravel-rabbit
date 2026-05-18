<?php

namespace Pushin\LaravelRabbit\Connections;

use PhpAmqpLib\Connection\AMQPConnectionConfig;
use Pushin\LaravelRabbit\Exceptions\ConfigurationException;
use Pushin\LaravelRabbit\Support\SecurityPolicy;

class ConnectionConfigFactory
{
    public function __construct(private readonly SecurityPolicy $securityPolicy)
    {
    }

    /**
     * @param array<string, mixed> $connection
     *
     * @return array<int, AMQPConnectionConfig>
     */
    public function makeAll(string $name, array $connection): array
    {
        $this->securityPolicy->assert($name, $connection);

        return array_map(
            fn (array $hostConfig): AMQPConnectionConfig => $this->make($name, $hostConfig),
            $this->hostConfigurations($connection),
        );
    }

    /**
     * @param array<string, mixed> $connection
     *
     * @return array<string, mixed>
     */
    public function sanitize(array $connection): array
    {
        return $this->securityPolicy->sanitize($connection);
    }

    /**
     * @param array<string, mixed> $connection
     *
     * @return array<int, array<string, mixed>>
     */
    private function hostConfigurations(array $connection): array
    {
        $base = $connection;
        unset($base['hosts']);

        $hosts = data_get($connection, 'hosts', []);

        if ($hosts === []) {
            return [$base];
        }

        return array_map(
            static fn (array $host): array => array_replace_recursive($base, $host),
            array_values($hosts),
        );
    }

    /**
     * @param array<string, mixed> $connection
     */
    private function make(string $name, array $connection): AMQPConnectionConfig
    {
        $config = new AMQPConnectionConfig();
        $sslEnabled = (bool) data_get($connection, 'ssl.enabled', false);

        $config->setHost((string) data_get($connection, 'host', '127.0.0.1'));
        $config->setPort((int) data_get($connection, 'port', $sslEnabled ? 5671 : 5672));
        $config->setUser((string) data_get($connection, 'user', 'guest'));
        $config->setPassword((string) data_get($connection, 'password', 'guest'));
        $config->setVhost((string) data_get($connection, 'vhost', '/'));
        $config->setIoType((string) data_get($connection, 'io_type', AMQPConnectionConfig::IO_TYPE_STREAM));
        $config->setIsLazy((bool) data_get($connection, 'lazy', false));
        $config->setInsist((bool) data_get($connection, 'insist', false));
        $config->setLoginMethod((string) data_get($connection, 'login_method', AMQPConnectionConfig::AUTH_AMQPPLAIN));

        if (($loginResponse = data_get($connection, 'login_response')) !== null) {
            $config->setLoginResponse((string) $loginResponse);
        }

        $config->setLocale((string) data_get($connection, 'locale', 'en_US'));
        $config->setConnectionTimeout((float) data_get($connection, 'connection_timeout', 3.0));
        $config->setReadTimeout((float) data_get($connection, 'read_timeout', data_get($connection, 'read_write_timeout', 3.0)));
        $config->setWriteTimeout((float) data_get($connection, 'write_timeout', data_get($connection, 'read_write_timeout', 3.0)));
        $config->setChannelRPCTimeout((float) data_get($connection, 'channel_rpc_timeout', 0.0));
        $config->setHeartbeat((int) data_get($connection, 'heartbeat', 60));
        $config->setKeepalive((bool) data_get($connection, 'keepalive', false));
        $config->setSendBufferSize((int) data_get($connection, 'send_buffer_size', 0));
        $config->enableSignalDispatch((bool) data_get($connection, 'dispatch_signals', true));
        $config->setProtocolStrictFields((bool) data_get($connection, 'protocol_strict_fields', false));
        $config->setDebugPackets((bool) data_get($connection, 'debug_packets', false));
        $config->setConnectionName((string) data_get($connection, 'connection_name', 'laravel-rabbit:' . $name));

        if ($sslEnabled) {
            $this->configureSsl($config, $connection);
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $connection
     */
    private function configureSsl(AMQPConnectionConfig $config, array $connection): void
    {
        if ($config->getIoType() === AMQPConnectionConfig::IO_TYPE_SOCKET) {
            throw new ConfigurationException('Secure RabbitMQ connections require io_type=stream.');
        }

        $config->setIsSecure(true);
        $config->setSslCaCert($this->nullableString(data_get($connection, 'ssl.cafile')));
        $config->setSslCaPath($this->nullableString(data_get($connection, 'ssl.capath')));
        $config->setSslCert($this->nullableString(data_get($connection, 'ssl.local_cert')));
        $config->setSslKey($this->nullableString(data_get($connection, 'ssl.local_pk')));
        $config->setSslPassPhrase($this->nullableString(data_get($connection, 'ssl.passphrase')));
        $config->setSslVerify(data_get($connection, 'ssl.verify_peer', true));
        $config->setSslVerifyName(data_get($connection, 'ssl.verify_peer_name', true));
        $config->setSslCiphers($this->nullableString(data_get($connection, 'ssl.ciphers')));

        if (($securityLevel = data_get($connection, 'ssl.security_level')) !== null) {
            $config->setSslSecurityLevel((int) $securityLevel);
        }

        if (($cryptoMethod = data_get($connection, 'ssl.crypto_method')) !== null) {
            $config->setSslCryptoMethod($this->parseCryptoMethod($cryptoMethod));
        }
    }

    private function parseCryptoMethod(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        if (is_string($value) && defined($value)) {
            return (int) constant($value);
        }

        throw new ConfigurationException(sprintf('Invalid RabbitMQ ssl.crypto_method value [%s].', (string) $value));
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
