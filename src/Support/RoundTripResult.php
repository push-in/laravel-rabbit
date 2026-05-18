<?php

namespace Pushin\LaravelRabbit\Support;

final class RoundTripResult
{
    public function __construct(
        public readonly bool $successful,
        public readonly string $message,
        public readonly ?string $messageId = null,
    ) {
    }
}
