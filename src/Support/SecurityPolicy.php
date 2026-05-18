<?php

namespace Pushin\LaravelRabbit\Support;

use Pushin\LaravelRabbit\Exceptions\SecurityException;

class SecurityPolicy
{
    /**
     * @param array<string, mixed> $connection
     */
    public function assert(string $name, array $connection): void
    {
        foreach ($this->hostConfigurations($connection) as $hostConfig) {
            $this->assertTlsPolicy($name, $hostConfig);
            $this->assertGuestPolicy($name, $hostConfig);
        }
    }

    /**
     * @param array<string, mixed> $connection
     *
     * @return array<string, mixed>
     */
    public function sanitize(array $connection): array
    {
        return $this->redact($connection);
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
    private function assertTlsPolicy(string $name, array $connection): void
    {
        $sslEnabled = (bool) data_get($connection, 'ssl.enabled', false);
        $requireTls = (bool) data_get($connection, 'security.require_tls', false);
        $allowInsecureTls = (bool) data_get($connection, 'security.allow_insecure_tls', false);
        $enforcePeerVerification = (bool) data_get($connection, 'security.enforce_tls_peer_verification', true);

        if ($requireTls && ! $sslEnabled) {
            throw new SecurityException(sprintf('RabbitMQ connection [%s] requires TLS, but ssl.enabled is false.', $name));
        }

        if (! $sslEnabled || ! $enforcePeerVerification || $allowInsecureTls) {
            return;
        }

        if (data_get($connection, 'ssl.verify_peer', true) === false) {
            throw new SecurityException(sprintf('RabbitMQ connection [%s] disables TLS peer verification.', $name));
        }

        if (data_get($connection, 'ssl.verify_peer_name', true) === false) {
            throw new SecurityException(sprintf('RabbitMQ connection [%s] disables TLS peer name verification.', $name));
        }
    }

    /**
     * @param array<string, mixed> $connection
     */
    private function assertGuestPolicy(string $name, array $connection): void
    {
        if (! (bool) data_get($connection, 'security.forbid_guest_on_remote_hosts', true)) {
            return;
        }

        if ((string) data_get($connection, 'user', 'guest') !== 'guest') {
            return;
        }

        $host = (string) data_get($connection, 'host', '127.0.0.1');

        if (! $this->isLocalHost($host)) {
            throw new SecurityException(sprintf(
                'RabbitMQ connection [%s] uses the guest user against remote host [%s].',
                $name,
                $host,
            ));
        }
    }

    private function isLocalHost(string $host): bool
    {
        return in_array(strtolower($host), ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true);
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = $this->redact($value);
                continue;
            }

            if ($this->isSensitiveKey((string) $key) && $value !== null && $value !== '') {
                $values[$key] = '[redacted]';
            }
        }

        return $values;
    }

    private function isSensitiveKey(string $key): bool
    {
        return in_array(strtolower($key), [
            'password',
            'passphrase',
            'secret',
            'token',
            'api_key',
            'app_key',
            'signing_key',
            'private_key',
            'local_pk',
            'client_key',
        ], true);
    }
}
