<?php

namespace Pushin\LaravelRabbit\Contracts;

interface RabbitManagementClient
{
    public function enabled(): bool;

    /**
     * @param array<string, mixed> $query
     *
     * @return array<int|string, mixed>|null
     */
    public function get(string $path, array $query = []): ?array;

    /**
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     *
     * @return array<int|string, mixed>|null
     */
    public function request(string $method, string $path, array $query = [], array|string|null $body = null, array $headers = []): ?array;

    /**
     * @return array<int|string, mixed>|null
     */
    public function overview(): ?array;

    /**
     * @return array<int, array<int|string, mixed>>
     */
    public function nodes(): array;

    /**
     * @return array<int, array<int|string, mixed>>
     */
    public function connections(): array;

    /**
     * @return array<int, array<int|string, mixed>>
     */
    public function channels(): array;

    /**
     * @return array<int, array<int|string, mixed>>
     */
    public function queues(?string $vhost = null): array;

    /**
     * @return array<int|string, mixed>|null
     */
    public function queue(string $queue, ?string $vhost = null): ?array;

    /**
     * @return array<int, array<int|string, mixed>>
     */
    public function exchanges(?string $vhost = null): array;

    /**
     * @return array<int, array<int|string, mixed>>
     */
    public function consumers(?string $vhost = null): array;

    /**
     * @return array<int, array<int|string, mixed>>
     */
    public function bindings(?string $vhost = null): array;

    /**
     * @return array<int, array<int|string, mixed>>
     */
    public function vhosts(): array;

    /**
     * @return array<int, array<int|string, mixed>>
     */
    public function users(): array;

    /**
     * @return array<int, array<int|string, mixed>>
     */
    public function permissions(): array;

    /**
     * @return array<int|string, mixed>|null
     */
    public function definitions(): ?array;

    /**
     * @return array<int|string, mixed>|null
     */
    public function alivenessTest(?string $vhost = null): ?array;
}
