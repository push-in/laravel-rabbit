<?php

namespace Pushin\LaravelRabbit\Support;

use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class MessageFactory
{
    /**
     * @param array<string, mixed> $properties
     */
    public function make(string $body, array $properties = []): AMQPMessage
    {
        return new AMQPMessage($body, $this->normalizeProperties($properties));
    }

    /**
     * @param array<string, mixed> $properties
     *
     * @return array<string, mixed>
     */
    public function normalizeProperties(array $properties): array
    {
        if (isset($properties['headers'])) {
            $headers = $properties['headers'];
            unset($properties['headers']);

            $applicationHeaders = $properties['application_headers'] ?? [];
            $properties['application_headers'] = array_replace(
                is_array($applicationHeaders) ? $applicationHeaders : [],
                is_array($headers) ? $headers : [],
            );
        }

        if (isset($properties['application_headers']) && is_array($properties['application_headers'])) {
            $properties['application_headers'] = new AMQPTable($properties['application_headers']);
        }

        return array_intersect_key($properties, array_flip([
            'content_type',
            'content_encoding',
            'application_headers',
            'delivery_mode',
            'priority',
            'correlation_id',
            'reply_to',
            'expiration',
            'message_id',
            'timestamp',
            'type',
            'user_id',
            'app_id',
            'cluster_id',
        ]));
    }
}
