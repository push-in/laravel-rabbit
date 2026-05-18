<?php

return [
    'connection' => env('LARAVEL_RABBIT_CONNECTION', 'default'),

    /*
     * This section is automatically exposed as queue.connections.rabbitmq.
     * Set QUEUE_CONNECTION=rabbitmq and Laravel jobs will use RabbitMQ through
     * the normal dispatch()->onQueue(), queue:work, release, backoff and tries APIs.
     */
    'laravel_queue' => [
        'driver' => 'rabbitmq',
        'rabbit_connection' => env('LARAVEL_RABBIT_CONNECTION', 'default'),
        'queue' => env('RABBITMQ_QUEUE', 'default'),
        'exchange' => env('RABBITMQ_QUEUE_EXCHANGE', env('RABBITMQ_EXCHANGE', '')),
        'exchange_type' => env('RABBITMQ_QUEUE_EXCHANGE_TYPE', 'direct'),
        'routing_key' => env('RABBITMQ_QUEUE_ROUTING_KEY'),
        'declare' => (bool) env('RABBITMQ_QUEUE_DECLARE', true),
        'durable' => (bool) env('RABBITMQ_QUEUE_DURABLE', true),
        'exclusive' => (bool) env('RABBITMQ_QUEUE_EXCLUSIVE', false),
        'auto_delete' => (bool) env('RABBITMQ_QUEUE_AUTO_DELETE', false),
        'exchange_durable' => (bool) env('RABBITMQ_QUEUE_EXCHANGE_DURABLE', true),
        'exchange_auto_delete' => (bool) env('RABBITMQ_QUEUE_EXCHANGE_AUTO_DELETE', false),
        'queue_arguments' => [],
        'exchange_arguments' => [],
        'delivery_mode' => (int) env('RABBITMQ_QUEUE_DELIVERY_MODE', 2),
        'mandatory' => (bool) env('RABBITMQ_QUEUE_MANDATORY', false),
        'wait_for_returns' => (bool) env('RABBITMQ_QUEUE_WAIT_FOR_RETURNS', false),
        'after_commit' => (bool) env('RABBITMQ_QUEUE_AFTER_COMMIT', false),
        'retry_after' => (int) env('RABBITMQ_RETRY_AFTER', 90),
        'block_for' => (float) env('RABBITMQ_QUEUE_BLOCK_FOR', 0),
        'headers' => [],
        'security' => [
            'sign_payloads' => (bool) env('RABBITMQ_QUEUE_SIGN_PAYLOADS', false),
            'verify_payload_signatures' => env('RABBITMQ_QUEUE_VERIFY_PAYLOAD_SIGNATURES') !== null
                ? (bool) env('RABBITMQ_QUEUE_VERIFY_PAYLOAD_SIGNATURES')
                : (bool) env('RABBITMQ_QUEUE_SIGN_PAYLOADS', false),
            'signing_key' => env('RABBITMQ_QUEUE_SIGNING_KEY', env('APP_KEY')),
            'invalid_signature_requeue' => (bool) env('RABBITMQ_QUEUE_INVALID_SIGNATURE_REQUEUE', false),
        ],
        'delay' => [
            'strategy' => env('RABBITMQ_QUEUE_DELAY_STRATEGY', 'ttl'),
            'queue_prefix' => env('RABBITMQ_QUEUE_DELAY_PREFIX', 'laravel.delay.'),
            'auto_delete' => (bool) env('RABBITMQ_QUEUE_DELAY_AUTO_DELETE', false),
            'exchange' => env('RABBITMQ_QUEUE_DELAY_EXCHANGE'),
            'queue_arguments' => [],
        ],
    ],

    /*
     * Optional RabbitMQ Management HTTP API integration.
     *
     * Enable it when the management plugin is available. The queue driver will
     * use it for accurate pending/reserved/delayed metrics while keeping AMQP as
     * the fallback for normal dispatching and consuming.
     */
    'management' => [
        'enabled' => (bool) env('RABBITMQ_MANAGEMENT_ENABLED', false),
        'scheme' => env('RABBITMQ_MANAGEMENT_SCHEME', 'http'),
        'host' => env('RABBITMQ_MANAGEMENT_HOST', env('RABBITMQ_HOST', '127.0.0.1')),
        'port' => (int) env('RABBITMQ_MANAGEMENT_PORT', 15672),
        'base_path' => env('RABBITMQ_MANAGEMENT_BASE_PATH', ''),
        'vhost' => env('RABBITMQ_MANAGEMENT_VHOST', env('RABBITMQ_VHOST', '/')),
        'user' => env('RABBITMQ_MANAGEMENT_USER', env('RABBITMQ_USER', 'guest')),
        'password' => env('RABBITMQ_MANAGEMENT_PASSWORD', env('RABBITMQ_PASSWORD', 'guest')),
        'timeout' => (float) env('RABBITMQ_MANAGEMENT_TIMEOUT', 5.0),
        'verify_tls' => (bool) env('RABBITMQ_MANAGEMENT_VERIFY_TLS', true),
        'allow_insecure_tls' => (bool) env('RABBITMQ_MANAGEMENT_ALLOW_INSECURE_TLS', false),
        'forbid_guest_on_remote_hosts' => (bool) env('RABBITMQ_MANAGEMENT_FORBID_GUEST_REMOTE', true),
        'cafile' => env('RABBITMQ_MANAGEMENT_CAFILE', env('RABBITMQ_SSL_CAFILE')),
        'capath' => env('RABBITMQ_MANAGEMENT_CAPATH', env('RABBITMQ_SSL_CAPATH')),
    ],

    'connections' => [
        'default' => [
            /*
             * You can use either a single host or a host list for failover.
             * Host entries inherit root credentials/options and may override them.
             */
            'host' => env('RABBITMQ_HOST', '127.0.0.1'),
            'port' => (int) env('RABBITMQ_PORT', env('RABBITMQ_SSL', false) ? 5671 : 5672),
            'vhost' => env('RABBITMQ_VHOST', '/'),
            'user' => env('RABBITMQ_USER', 'guest'),
            'password' => env('RABBITMQ_PASSWORD', 'guest'),
            'hosts' => [],

            'connection_name' => env('RABBITMQ_CONNECTION_NAME', 'laravel-rabbit'),
            'io_type' => env('RABBITMQ_IO_TYPE', 'stream'),
            'lazy' => (bool) env('RABBITMQ_LAZY', false),
            'insist' => (bool) env('RABBITMQ_INSIST', false),
            'login_method' => env('RABBITMQ_LOGIN_METHOD', 'AMQPLAIN'),
            'login_response' => env('RABBITMQ_LOGIN_RESPONSE'),
            'locale' => env('RABBITMQ_LOCALE', 'en_US'),
            'connection_timeout' => (float) env('RABBITMQ_CONNECTION_TIMEOUT', 3.0),
            'read_timeout' => (float) env('RABBITMQ_READ_TIMEOUT', 3.0),
            'write_timeout' => (float) env('RABBITMQ_WRITE_TIMEOUT', 3.0),
            'channel_rpc_timeout' => (float) env('RABBITMQ_CHANNEL_RPC_TIMEOUT', 0.0),
            'heartbeat' => (int) env('RABBITMQ_HEARTBEAT', 60),
            'keepalive' => (bool) env('RABBITMQ_KEEPALIVE', false),
            'send_buffer_size' => (int) env('RABBITMQ_SEND_BUFFER_SIZE', 0),
            'dispatch_signals' => (bool) env('RABBITMQ_DISPATCH_SIGNALS', true),
            'protocol_strict_fields' => (bool) env('RABBITMQ_PROTOCOL_STRICT_FIELDS', false),
            'debug_packets' => (bool) env('RABBITMQ_DEBUG_PACKETS', false),

            'reconnect' => [
                'attempts' => (int) env('RABBITMQ_RECONNECT_ATTEMPTS', 1),
                'sleep_ms' => (int) env('RABBITMQ_RECONNECT_SLEEP_MS', 250),
                'multiplier' => (float) env('RABBITMQ_RECONNECT_MULTIPLIER', 1.5),
            ],

            'ssl' => [
                'enabled' => (bool) env('RABBITMQ_SSL', false),
                'cafile' => env('RABBITMQ_SSL_CAFILE'),
                'capath' => env('RABBITMQ_SSL_CAPATH'),
                'local_cert' => env('RABBITMQ_SSL_LOCAL_CERT'),
                'local_pk' => env('RABBITMQ_SSL_LOCAL_PK'),
                'passphrase' => env('RABBITMQ_SSL_PASSPHRASE'),
                'verify_peer' => (bool) env('RABBITMQ_SSL_VERIFY_PEER', true),
                'verify_peer_name' => (bool) env('RABBITMQ_SSL_VERIFY_PEER_NAME', true),
                'ciphers' => env('RABBITMQ_SSL_CIPHERS'),
                'security_level' => env('RABBITMQ_SSL_SECURITY_LEVEL') !== null
                    ? (int) env('RABBITMQ_SSL_SECURITY_LEVEL')
                    : null,
                'crypto_method' => env('RABBITMQ_SSL_CRYPTO_METHOD'),
            ],

            'security' => [
                'require_tls' => (bool) env('RABBITMQ_REQUIRE_TLS', false),
                'allow_insecure_tls' => (bool) env('RABBITMQ_ALLOW_INSECURE_TLS', false),
                'enforce_tls_peer_verification' => (bool) env('RABBITMQ_ENFORCE_TLS_PEER_VERIFICATION', true),
                'forbid_guest_on_remote_hosts' => (bool) env('RABBITMQ_FORBID_GUEST_REMOTE', true),
                'max_message_size' => env('RABBITMQ_MAX_MESSAGE_SIZE') !== null
                    ? (int) env('RABBITMQ_MAX_MESSAGE_SIZE')
                    : null,
            ],

            'publisher_confirms' => [
                'enabled' => (bool) env('RABBITMQ_PUBLISHER_CONFIRMS', true),
                'wait' => (bool) env('RABBITMQ_PUBLISHER_CONFIRMS_WAIT', true),
                'timeout' => (float) env('RABBITMQ_PUBLISHER_CONFIRMS_TIMEOUT', 5.0),
                'wait_for_returns' => (bool) env('RABBITMQ_PUBLISHER_CONFIRMS_WAIT_RETURNS', false),
            ],

            'qos' => [
                'enabled' => (bool) env('RABBITMQ_QOS_ENABLED', true),
                'prefetch_size' => (int) env('RABBITMQ_QOS_PREFETCH_SIZE', 0),
                'prefetch_count' => (int) env('RABBITMQ_QOS_PREFETCH_COUNT', 10),
                'global' => (bool) env('RABBITMQ_QOS_GLOBAL', false),
            ],

            'message' => [
                'content_type' => env('RABBITMQ_MESSAGE_CONTENT_TYPE', 'text/plain'),
                'delivery_mode' => (int) env('RABBITMQ_MESSAGE_DELIVERY_MODE', 2),
                'app_id' => env('APP_NAME', 'laravel'),
                'headers' => [],
            ],

            'exchange' => [
                'name' => env('RABBITMQ_EXCHANGE', ''),
                'type' => env('RABBITMQ_EXCHANGE_TYPE', 'direct'),
                'passive' => (bool) env('RABBITMQ_EXCHANGE_PASSIVE', false),
                'durable' => (bool) env('RABBITMQ_EXCHANGE_DURABLE', true),
                'auto_delete' => (bool) env('RABBITMQ_EXCHANGE_AUTO_DELETE', false),
                'internal' => (bool) env('RABBITMQ_EXCHANGE_INTERNAL', false),
                'nowait' => (bool) env('RABBITMQ_EXCHANGE_NOWAIT', false),
                'arguments' => [],
                'ticket' => null,
                'declare' => env('RABBITMQ_EXCHANGE', '') !== '',
            ],

            'queue' => [
                'name' => env('RABBITMQ_QUEUE', 'default'),
                'passive' => (bool) env('RABBITMQ_QUEUE_PASSIVE', false),
                'durable' => (bool) env('RABBITMQ_QUEUE_DURABLE', true),
                'exclusive' => (bool) env('RABBITMQ_QUEUE_EXCLUSIVE', false),
                'auto_delete' => (bool) env('RABBITMQ_QUEUE_AUTO_DELETE', false),
                'nowait' => (bool) env('RABBITMQ_QUEUE_NOWAIT', false),
                'arguments' => [],
                'ticket' => null,
                'declare' => (bool) env('RABBITMQ_QUEUE_DECLARE', true),
                'bindings' => [
                    [
                        'exchange' => env('RABBITMQ_EXCHANGE', ''),
                        'routing_key' => env('RABBITMQ_ROUTING_KEY', env('RABBITMQ_QUEUE', 'default')),
                        'nowait' => false,
                        'arguments' => [],
                        'ticket' => null,
                    ],
                ],
            ],

            'publish' => [
                'exchange' => env('RABBITMQ_EXCHANGE', ''),
                'routing_key' => env('RABBITMQ_ROUTING_KEY', env('RABBITMQ_QUEUE', 'default')),
                'mandatory' => (bool) env('RABBITMQ_PUBLISH_MANDATORY', false),
                'immediate' => (bool) env('RABBITMQ_PUBLISH_IMMEDIATE', false),
                'ticket' => null,
            ],

            'consumer' => [
                'tag' => env('RABBITMQ_CONSUMER_TAG', ''),
                'no_local' => false,
                'no_ack' => false,
                'exclusive' => false,
                'nowait' => false,
                'ticket' => null,
                'arguments' => [],
                'wait_timeout' => (float) env('RABBITMQ_CONSUMER_WAIT_TIMEOUT', 1.0),
                'idle_timeout' => env('RABBITMQ_CONSUMER_IDLE_TIMEOUT') !== null
                    ? (float) env('RABBITMQ_CONSUMER_IDLE_TIMEOUT')
                    : null,
                'max_messages' => env('RABBITMQ_CONSUMER_MAX_MESSAGES') !== null
                    ? (int) env('RABBITMQ_CONSUMER_MAX_MESSAGES')
                    : null,
                'stop_when_empty' => (bool) env('RABBITMQ_CONSUMER_STOP_WHEN_EMPTY', false),
                'ack_on_success' => true,
                'nack_on_false' => true,
                'nack_on_false_requeue' => false,
                'reject_on_exception' => true,
                'reject_on_exception_requeue' => false,
            ],

            'topology' => [
                'auto_declare' => (bool) env('RABBITMQ_TOPOLOGY_AUTO_DECLARE', true),
                'exchanges' => [],
                'queues' => [],
                'bindings' => [],
            ],
        ],
    ],
];
