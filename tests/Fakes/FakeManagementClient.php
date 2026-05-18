<?php

namespace Pushin\LaravelRabbit\Tests\Fakes;

use Pushin\LaravelRabbit\Contracts\RabbitManagementClient;

class FakeManagementClient implements RabbitManagementClient
{
    /**
     * @param array<string, array<int|string, mixed>> $queueStats
     * @param array<string, array<int, array<int|string, mixed>>> $queueLists
     * @param array<int|string, mixed>|null $overview
     */
    public function __construct(
        private readonly bool $enabled = true,
        public array $queueStats = [],
        public array $queueLists = [],
        public ?array $overview = null,
        public ?array $aliveness = ['status' => 'ok'],
    ) {
    }

    public function enabled(): bool
    {
        return $this->enabled;
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
        return $this->overview;
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
        return $this->queueLists[$vhost ?? '/'] ?? [];
    }

    public function queue(string $queue, ?string $vhost = null): ?array
    {
        return $this->queueStats[($vhost ?? '/') . "\0" . $queue] ?? null;
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
        return $this->aliveness;
    }
}
