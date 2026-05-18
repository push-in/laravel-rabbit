<?php

namespace Pushin\LaravelRabbit\Exceptions;

class ManagementException extends LaravelRabbitException
{
    public static function requestFailed(string $url, string $reason): self
    {
        return new self(sprintf('RabbitMQ Management API request failed for [%s]: %s', $url, $reason));
    }

    public static function httpError(string $url, int $status, string $body): self
    {
        $body = trim($body);

        if (strlen($body) > 300) {
            $body = substr($body, 0, 300) . '...';
        }

        return new self(sprintf(
            'RabbitMQ Management API request to [%s] returned HTTP %d%s',
            $url,
            $status,
            $body === '' ? '.' : ": {$body}",
        ));
    }

    public static function invalidJson(string $url, string $reason): self
    {
        return new self(sprintf('RabbitMQ Management API returned invalid JSON for [%s]: %s', $url, $reason));
    }
}
