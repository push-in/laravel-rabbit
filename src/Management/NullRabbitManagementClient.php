<?php

namespace Pushin\LaravelRabbit\Management;

use Pushin\LaravelRabbit\Contracts\RabbitManagementClient;

class NullRabbitManagementClient implements RabbitManagementClient
{
    public function enabled(): bool
    {
        return false;
    }

    public function get(string $path, array $query = []): ?array
    {
        return null;
    }

    public function request(string $method, string $path, array $query = [], array|string|null $body = null, array $headers = []): ?array
    {
        return null;
    }

    public function overview(): ?array
    {
        return null;
    }

    public function nodes(): array
    {
        return [];
    }

    public function connections(): array
    {
        return [];
    }

    public function channels(): array
    {
        return [];
    }

    public function queues(?string $vhost = null): array
    {
        return [];
    }

    public function queue(string $queue, ?string $vhost = null): ?array
    {
        return null;
    }

    public function exchanges(?string $vhost = null): array
    {
        return [];
    }

    public function consumers(?string $vhost = null): array
    {
        return [];
    }

    public function bindings(?string $vhost = null): array
    {
        return [];
    }

    public function vhosts(): array
    {
        return [];
    }

    public function users(): array
    {
        return [];
    }

    public function permissions(): array
    {
        return [];
    }

    public function definitions(): ?array
    {
        return null;
    }

    public function alivenessTest(?string $vhost = null): ?array
    {
        return null;
    }
}
