<?php

namespace Pushin\LaravelRabbit\Management;

use Pushin\LaravelRabbit\Contracts\RabbitManagementClient;
use Pushin\LaravelRabbit\Exceptions\ConfigurationException;
use Pushin\LaravelRabbit\Exceptions\ManagementException;
use Pushin\LaravelRabbit\Exceptions\SecurityException;

class HttpRabbitManagementClient implements RabbitManagementClient
{
    /** @var array<string, mixed> */
    private readonly array $config;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $this->config = array_replace([
            'enabled' => false,
            'scheme' => 'http',
            'host' => '127.0.0.1',
            'port' => 15672,
            'base_path' => '',
            'vhost' => '/',
            'user' => 'guest',
            'password' => 'guest',
            'timeout' => 5.0,
            'verify_tls' => true,
            'allow_insecure_tls' => false,
            'forbid_guest_on_remote_hosts' => true,
            'cafile' => null,
            'capath' => null,
        ], $config);

        $this->assertConfiguration();
        $this->assertSecurity();
    }

    public function enabled(): bool
    {
        return (bool) $this->config['enabled'];
    }

    public function get(string $path, array $query = []): ?array
    {
        return $this->request('GET', $path, $query);
    }

    public function request(string $method, string $path, array $query = [], array|string|null $body = null, array $headers = []): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $url = $this->url($path, $query);
        $context = stream_context_create($this->streamContext($method, $body, $headers));
        $warning = null;

        set_error_handler(static function (int $severity, string $message) use (&$warning): bool {
            $warning = $message;

            return true;
        });

        try {
            $body = file_get_contents($url, false, $context);
        } finally {
            restore_error_handler();
        }

        /** @var array<int, string> $http_response_header */
        $headers = $http_response_header ?? [];

        if ($body === false) {
            throw ManagementException::requestFailed($this->redactedUrl($url), $warning ?: 'no response received');
        }

        $status = $this->statusCode($headers);

        if ($status === 404) {
            return null;
        }

        if ($status < 200 || $status >= 300) {
            throw ManagementException::httpError($this->redactedUrl($url), $status, $body);
        }

        if ($body === '') {
            return [];
        }

        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            throw ManagementException::invalidJson($this->redactedUrl($url), json_last_error_msg());
        }

        return $decoded;
    }

    public function overview(): ?array
    {
        return $this->get('/api/overview');
    }

    public function nodes(): array
    {
        return $this->list('/api/nodes');
    }

    public function connections(): array
    {
        return $this->list('/api/connections');
    }

    public function channels(): array
    {
        return $this->list('/api/channels');
    }

    public function queues(?string $vhost = null): array
    {
        return $this->list(sprintf('/api/queues/%s', $this->encodeVhost($vhost)));
    }

    public function queue(string $queue, ?string $vhost = null): ?array
    {
        return $this->get(sprintf('/api/queues/%s/%s', $this->encodeVhost($vhost), rawurlencode($queue)));
    }

    public function exchanges(?string $vhost = null): array
    {
        return $this->list(sprintf('/api/exchanges/%s', $this->encodeVhost($vhost)));
    }

    public function consumers(?string $vhost = null): array
    {
        return $this->list(sprintf('/api/consumers/%s', $this->encodeVhost($vhost)));
    }

    public function bindings(?string $vhost = null): array
    {
        return $this->list(sprintf('/api/bindings/%s', $this->encodeVhost($vhost)));
    }

    public function vhosts(): array
    {
        return $this->list('/api/vhosts');
    }

    public function users(): array
    {
        return $this->list('/api/users');
    }

    public function permissions(): array
    {
        return $this->list('/api/permissions');
    }

    public function definitions(): ?array
    {
        return $this->get('/api/definitions');
    }

    public function alivenessTest(?string $vhost = null): ?array
    {
        return $this->get(sprintf('/api/aliveness-test/%s', $this->encodeVhost($vhost)));
    }

    /**
     * @return array<int, array<int|string, mixed>>
     */
    private function list(string $path): array
    {
        $result = $this->get($path);

        if ($result === null) {
            return [];
        }

        return array_values(array_filter($result, 'is_array'));
    }

    /**
     * @param array<string, mixed> $query
     */
    private function url(string $path, array $query = []): string
    {
        $path = $this->apiPath($path);
        $basePath = trim((string) $this->config['base_path'], '/');
        $prefix = $basePath === '' ? '' : '/' . $basePath;
        $url = sprintf(
            '%s://%s:%d%s%s',
            (string) $this->config['scheme'],
            $this->hostForUrl((string) $this->config['host']),
            (int) $this->config['port'],
            $prefix,
            $path,
        );

        if ($query !== []) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        return $url;
    }

    private function apiPath(string $path): string
    {
        $path = '/' . ltrim($path, '/');

        if (! str_starts_with($path, '/api/')) {
            $path = '/api' . $path;
        }

        return $path;
    }

    /**
     * @param array<string, string> $headers
     *
     * @return array<string, mixed>
     */
    private function streamContext(string $method, array|string|null $body, array $headers): array
    {
        $headerLines = [
            'Accept' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode((string) $this->config['user'] . ':' . (string) $this->config['password']),
            'User-Agent' => 'pushin-laravel-rabbit',
        ];

        foreach ($headers as $name => $value) {
            $headerLines[$name] = $value;
        }

        $content = null;

        if (is_array($body)) {
            $content = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $headerLines['Content-Type'] ??= 'application/json';
        } elseif (is_string($body)) {
            $content = $body;
        }

        if ($content !== null) {
            $headerLines['Content-Length'] = (string) strlen($content);
        }

        $context = [
            'http' => [
                'method' => strtoupper($method),
                'header' => implode("\r\n", array_map(
                    static fn (string $name, string $value): string => $name . ': ' . $value,
                    array_keys($headerLines),
                    $headerLines,
                )),
                'timeout' => (float) $this->config['timeout'],
                'ignore_errors' => true,
            ],
        ];

        if ($content !== null) {
            $context['http']['content'] = $content;
        }

        if ((string) $this->config['scheme'] === 'https') {
            $context['ssl'] = [
                'verify_peer' => (bool) $this->config['verify_tls'],
                'verify_peer_name' => (bool) $this->config['verify_tls'],
            ];

            if ($this->config['cafile'] !== null && $this->config['cafile'] !== '') {
                $context['ssl']['cafile'] = (string) $this->config['cafile'];
            }

            if ($this->config['capath'] !== null && $this->config['capath'] !== '') {
                $context['ssl']['capath'] = (string) $this->config['capath'];
            }
        }

        return $context;
    }

    /**
     * @param array<int, string> $headers
     */
    private function statusCode(array $headers): int
    {
        $status = 0;

        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/', $header, $matches) === 1) {
                $status = (int) $matches[1];
            }
        }

        return $status;
    }

    private function encodeVhost(?string $vhost): string
    {
        return rawurlencode($vhost ?? (string) $this->config['vhost']);
    }

    private function hostForUrl(string $host): string
    {
        if (str_contains($host, ':') && ! str_starts_with($host, '[')) {
            return '[' . $host . ']';
        }

        return $host;
    }

    private function redactedUrl(string $url): string
    {
        return preg_replace('/([?&](?:password|token|api_key|secret)=)[^&]*/i', '$1[redacted]', $url) ?? $url;
    }

    private function assertConfiguration(): void
    {
        $scheme = (string) $this->config['scheme'];

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new ConfigurationException(sprintf('RabbitMQ Management API scheme [%s] is not supported.', $scheme));
        }
    }

    private function assertSecurity(): void
    {
        if (! $this->enabled()) {
            return;
        }

        if (
            (string) $this->config['scheme'] === 'https'
            && ! (bool) $this->config['verify_tls']
            && ! (bool) $this->config['allow_insecure_tls']
        ) {
            throw new SecurityException('RabbitMQ Management API disables TLS verification. Set RABBITMQ_MANAGEMENT_ALLOW_INSECURE_TLS=true only for trusted local development.');
        }

        if (
            (bool) $this->config['forbid_guest_on_remote_hosts']
            && (string) $this->config['user'] === 'guest'
            && ! $this->isLocalHost((string) $this->config['host'])
        ) {
            throw new SecurityException(sprintf(
                'RabbitMQ Management API uses the guest user against remote host [%s].',
                (string) $this->config['host'],
            ));
        }
    }

    private function isLocalHost(string $host): bool
    {
        return in_array(strtolower($host), ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true);
    }
}
