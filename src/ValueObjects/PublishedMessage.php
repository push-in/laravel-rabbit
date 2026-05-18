<?php

namespace Pushin\LaravelRabbit\ValueObjects;

final class PublishedMessage
{
    /**
     * @param array<string, mixed> $properties
     */
    public function __construct(
        public readonly string $exchange,
        public readonly string $routingKey,
        public readonly int $bodySize,
        public readonly array $properties,
        public readonly bool $confirmed,
    ) {
    }
}
