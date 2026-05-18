# Laravel Rabbit

[![Tests](https://github.com/push-in/laravel-rabbit/actions/workflows/tests.yml/badge.svg)](https://github.com/push-in/laravel-rabbit/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/pushinbr/laravel-rabbit.svg)](https://packagist.org/packages/pushinbr/laravel-rabbit)
[![Total Downloads](https://img.shields.io/packagist/dt/pushinbr/laravel-rabbit.svg)](https://packagist.org/packages/pushinbr/laravel-rabbit)
[![License](https://img.shields.io/packagist/l/pushinbr/laravel-rabbit.svg)](LICENSE.md)

RabbitMQ library for Laravel with a native queue driver, AMQP 0-9-1 publishing and consuming, topology declarations, TLS, publisher confirms, QoS, host failover, RabbitMQ Management HTTP API support, and security validation.

```php
use App\Jobs\ProcessOrder;

ProcessOrder::dispatch($order)->onQueue('orders');
```

> [!IMPORTANT]
> The package registers a Laravel queue driver named `rabbitmq`. When `QUEUE_CONNECTION=rabbitmq`, Laravel's native `dispatch()`, `onQueue()`, `delay()`, `queue:work`, retries, backoff, failed jobs, batches, chained jobs, and unique jobs continue to work through the standard Laravel worker flow.

## Table of Contents

- [Compatibility](#compatibility)
- [Feature Overview](#feature-overview)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Laravel Queue Driver](#laravel-queue-driver)
- [Delayed Jobs](#delayed-jobs)
- [RabbitMQ Management API](#rabbitmq-management-api)
- [Artisan Commands](#artisan-commands)
- [Low-Level AMQP Usage](#low-level-amqp-usage)
- [Topology](#topology)
- [Production Options](#production-options)
- [Security](#security)
- [Testing](#testing)

## Compatibility

| Component | Supported Versions | Notes |
| --- | --- | --- |
| PHP | `^8.2` | Tested on modern PHP versions used by Laravel 11, 12, and 13. |
| Laravel | `^11.0`, `^12.0`, `^13.0` | Package discovery registers the service provider and facade automatically. |
| Queue system | Laravel Queue | Adds `queue.connections.rabbitmq` and works with the normal worker commands. |
| AMQP driver | `php-amqplib/php-amqplib ^3.7` | Used for AMQP 0-9-1 connections and messages. |
| RabbitMQ | RabbitMQ 3.x and 4.x compatible AMQP API | Management API metrics require the RabbitMQ management plugin. |

## Feature Overview

| Area | Included |
| --- | --- |
| Laravel queue driver | `dispatch()`, `onQueue()`, `delay()`, `release()`, `queue:work`, `queue:clear`, `tries`, `backoff`, `timeout`, `retryUntil`, failed jobs, batches, chained jobs, and unique jobs. |
| AMQP publishing | Plain text, JSON, headers, message properties, mandatory publish, and publisher confirms. |
| AMQP consuming | Callback consumers, `basic_get`, ack, nack, reject, idle timeout, max messages, and stop when empty. |
| Delay support | TTL dead-letter queues by default, optional `x-delayed-message` exchange strategy. |
| Broker topology | Exchanges, queues, bindings, queue arguments, exchange arguments, and automatic declarations. |
| Operations | `rabbitmq:install`, `rabbitmq:check`, `rabbitmq:setup`, `rabbitmq:stats`, `rabbitmq:management`, `rabbitmq:doctor`, `rabbitmq:consume-test`, and `rabbitmq:purge`. |
| Management API | Overview, nodes, queues, queue metrics, exchanges, consumers, bindings, users, permissions, definitions, aliveness test, and generic HTTP requests. |
| Security | Queue payload signing, TLS policy, remote `guest` guard, message size guard, Management API TLS policy, and config sanitization. |
| Reliability | Publisher confirms, QoS/prefetch, heartbeat, timeouts, host failover, reconnect attempts, and return listeners. |

## Installation

Install the package:

```bash
composer require pushinbr/laravel-rabbit
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=laravel-rabbit-config
```

Or use the installer command:

```bash
php artisan rabbitmq:install
```

Laravel auto-discovery registers:

| Binding | Purpose |
| --- | --- |
| `Pushin\LaravelRabbit\LaravelRabbit` | Main package service. |
| `laravel-rabbit` | Container alias for the main service. |
| `laravel-rabbit.manager` | Connection manager alias. |
| `laravel-rabbit.management` | RabbitMQ Management API client alias. |
| `LaravelRabbit` facade | Optional facade shortcut. |

```php
use Pushin\LaravelRabbit\Facades\LaravelRabbit;
```

## Quick Start

### 1. Configure RabbitMQ

```dotenv
QUEUE_CONNECTION=rabbitmq

LARAVEL_RABBIT_CONNECTION=default
RABBITMQ_HOST=127.0.0.1
RABBITMQ_PORT=5672
RABBITMQ_VHOST=/
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_QUEUE=default
RABBITMQ_EXCHANGE=
RABBITMQ_ROUTING_KEY=
```

> [!TIP]
> Leave `RABBITMQ_QUEUE_ROUTING_KEY` unset for most Laravel apps. The package will use the queue name from `onQueue()` as the routing key.

Optional payload signing for the queue driver:

```dotenv
RABBITMQ_QUEUE_SIGN_PAYLOADS=true
RABBITMQ_QUEUE_SIGNING_KEY="${APP_KEY}"
RABBITMQ_QUEUE_INVALID_SIGNATURE_REQUEUE=false
```

> [!IMPORTANT]
> Payload signing protects the Laravel worker from unsigned or tampered queue messages. Enable it only after all producers that publish Laravel jobs through RabbitMQ use the same signing key, because unsigned existing messages will be rejected when verification is enabled.

### 2. Dispatch a Job

```php
use App\Jobs\ProcessOrder;

ProcessOrder::dispatch($order)->onQueue('orders');
```

### 3. Run the Worker

```bash
php artisan queue:work --queue=orders
```

Because `QUEUE_CONNECTION=rabbitmq`, the command above uses RabbitMQ without passing the connection name. This also works:

```bash
php artisan queue:work rabbitmq --queue=orders
```

### 4. Check the Setup

```bash
php artisan rabbitmq:doctor
php artisan rabbitmq:stats orders
```

## Laravel Queue Driver

The `laravel_queue` config section is merged automatically into `queue.connections.rabbitmq`.

```php
'laravel_queue' => [
    'driver' => 'rabbitmq',
    'rabbit_connection' => env('LARAVEL_RABBIT_CONNECTION', 'default'),
    'queue' => env('RABBITMQ_QUEUE', 'default'),
    'exchange' => env('RABBITMQ_QUEUE_EXCHANGE', env('RABBITMQ_EXCHANGE', '')),
    'exchange_type' => env('RABBITMQ_QUEUE_EXCHANGE_TYPE', 'direct'),
    'routing_key' => env('RABBITMQ_QUEUE_ROUTING_KEY'),
    'declare' => true,
    'durable' => true,
    'delivery_mode' => 2,
    'after_commit' => false,
    'retry_after' => 90,
    'security' => [
        'sign_payloads' => false,
        'verify_payload_signatures' => false,
        'signing_key' => env('APP_KEY'),
        'invalid_signature_requeue' => false,
    ],
    'delay' => [
        'strategy' => 'ttl',
        'queue_prefix' => 'laravel.delay.',
    ],
],
```

### Laravel Support Matrix

| Laravel feature | Status | Implementation detail |
| --- | --- | --- |
| `dispatch()` | Supported | Publishes a Laravel queue payload to RabbitMQ. |
| `dispatch()->onQueue('orders')` | Supported | Uses `orders` as the RabbitMQ queue and default routing key. |
| `delay()` / `later()` | Supported | Uses TTL dead-letter queues by default. |
| `release($delay)` | Supported | Republishes with incremented attempts and acknowledges the original delivery. |
| `$tries` | Supported | Enforced by Laravel's worker. |
| `backoff()` / `$backoff` | Supported | Enforced by Laravel's worker through `release($delay)`. |
| `$timeout` | Supported | Enforced by Laravel's worker process. |
| `retryUntil()` | Supported | Enforced by Laravel's worker. |
| Failed jobs | Supported | Uses Laravel's configured failed job provider. |
| Chained jobs | Supported | Laravel handles the chain metadata in the payload. |
| Batches | Supported | Laravel handles batch metadata in the payload. |
| Unique jobs | Supported | Laravel handles locks before enqueueing. |
| `queue:work` | Supported | Works as `queue:work` when `QUEUE_CONNECTION=rabbitmq`. |
| `queue:clear` | Supported | Purges the target RabbitMQ queue. |

### Worker Examples

| Goal | Command |
| --- | --- |
| Work the default RabbitMQ connection | `php artisan queue:work` |
| Work one queue | `php artisan queue:work --queue=orders` |
| Explicit RabbitMQ connection | `php artisan queue:work rabbitmq --queue=orders` |
| Process one job | `php artisan queue:work --queue=orders --once` |
| Clear a queue | `php artisan queue:clear rabbitmq --queue=orders` |
| Retry failed jobs | `php artisan queue:retry all` |

### Job Example

```php
namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessOrder implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    public function __construct(public int $orderId)
    {
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(): void
    {
        // Process the order.
    }
}
```

```php
ProcessOrder::dispatch($order->id)
    ->onQueue('orders')
    ->delay(now()->addMinutes(5));
```

## Delayed Jobs

| Strategy | Requires plugin | How it works | Metric support |
| --- | --- | --- | --- |
| `ttl` | No | Publishes to a per-delay TTL queue that dead-letters back to the target queue. | `delayed` can be calculated through the Management API. |
| `x-delayed-message` | Yes | Publishes through RabbitMQ's delayed message exchange plugin. | RabbitMQ does not expose delayed exchange messages as queue depth. |
| `none` | No | Publishes immediately. | No delayed count. |

Default strategy:

```dotenv
RABBITMQ_QUEUE_DELAY_STRATEGY=ttl
RABBITMQ_QUEUE_DELAY_PREFIX=laravel.delay.
```

Delayed message exchange strategy:

```dotenv
RABBITMQ_QUEUE_DELAY_STRATEGY=x-delayed-message
RABBITMQ_QUEUE_DELAY_EXCHANGE=laravel.delayed
```

## RabbitMQ Management API

The Management API integration is optional. The queue driver can dispatch and consume jobs without it. Enable it when you want broker-level metrics and operational visibility.

```dotenv
RABBITMQ_MANAGEMENT_ENABLED=true
RABBITMQ_MANAGEMENT_SCHEME=http
RABBITMQ_MANAGEMENT_HOST=127.0.0.1
RABBITMQ_MANAGEMENT_PORT=15672
RABBITMQ_MANAGEMENT_USER=guest
RABBITMQ_MANAGEMENT_PASSWORD=guest
```

### Management Configuration

| Env | Default | Description |
| --- | --- | --- |
| `RABBITMQ_MANAGEMENT_ENABLED` | `false` | Enables the Management API client. |
| `RABBITMQ_MANAGEMENT_SCHEME` | `http` | Use `http` or `https`. |
| `RABBITMQ_MANAGEMENT_HOST` | `RABBITMQ_HOST` or `127.0.0.1` | Management API host. |
| `RABBITMQ_MANAGEMENT_PORT` | `15672` | Management API port. |
| `RABBITMQ_MANAGEMENT_BASE_PATH` | empty | Optional reverse proxy base path. |
| `RABBITMQ_MANAGEMENT_VHOST` | `RABBITMQ_VHOST` or `/` | Default virtual host for API calls. |
| `RABBITMQ_MANAGEMENT_USER` | `RABBITMQ_USER` or `guest` | Management API user. |
| `RABBITMQ_MANAGEMENT_PASSWORD` | `RABBITMQ_PASSWORD` or `guest` | Management API password. |
| `RABBITMQ_MANAGEMENT_TIMEOUT` | `5.0` | HTTP timeout in seconds. |
| `RABBITMQ_MANAGEMENT_VERIFY_TLS` | `true` | Verifies TLS peer and peer name for HTTPS. |
| `RABBITMQ_MANAGEMENT_ALLOW_INSECURE_TLS` | `false` | Allows disabled HTTPS verification when explicitly needed. |
| `RABBITMQ_MANAGEMENT_FORBID_GUEST_REMOTE` | `true` | Rejects `guest` on non-local hosts. |
| `RABBITMQ_MANAGEMENT_CAFILE` | `RABBITMQ_SSL_CAFILE` | Optional CA file for HTTPS. |
| `RABBITMQ_MANAGEMENT_CAPATH` | `RABBITMQ_SSL_CAPATH` | Optional CA path for HTTPS. |

### Metrics Mapping

| Laravel queue metric | RabbitMQ Management field | Fallback without Management API |
| --- | --- | --- |
| `size()` / `total` | `messages` | AMQP queue declaration message count. |
| `pendingSize()` / `pending` | `messages_ready` | AMQP queue declaration message count. |
| `reservedSize()` / `reserved` | `messages_unacknowledged` | `0` |
| `delayedSize()` / `delayed` | Sum of matching TTL delay queues | `0` |

> [!NOTE]
> `delayedSize()` is accurate for this package's default TTL delay queues. RabbitMQ does not expose queued messages inside an `x-delayed-message` exchange as normal queue depth.

### Management Client Usage

```php
use Pushin\LaravelRabbit\Facades\LaravelRabbit;

$overview = LaravelRabbit::management()->overview();
$orders = LaravelRabbit::management()->queue('orders');
$queues = LaravelRabbit::management()->queues('/');
$nodes = LaravelRabbit::management()->nodes();
$definitions = LaravelRabbit::management()->definitions();
```

Call any Management API endpoint directly:

```php
$policies = LaravelRabbit::management()->get('/api/policies/%2F');
```

Use non-GET endpoints through `request()`:

```php
LaravelRabbit::management()->request('PUT', '/api/policies/%2F/ttl', body: [
    'pattern' => '^orders\\.',
    'definition' => ['message-ttl' => 60000],
    'priority' => 0,
    'apply-to' => 'queues',
]);
```

## Artisan Commands

Laravel's native queue commands remain the primary interface.

| Command | Purpose |
| --- | --- |
| `php artisan queue:work --queue=orders` | Work the `orders` queue using RabbitMQ when `QUEUE_CONNECTION=rabbitmq`. |
| `php artisan queue:work rabbitmq --queue=orders` | Work the `orders` queue with an explicit queue connection. |
| `php artisan queue:clear rabbitmq --queue=orders` | Clear a queue through Laravel's native queue command. |
| `php artisan queue:failed` | List failed jobs from Laravel's failed job provider. |
| `php artisan queue:retry all` | Retry failed jobs through Laravel. |

The package also adds RabbitMQ-specific operational commands.

| Command | Purpose | Safe for deploy checks |
| --- | --- | --- |
| `rabbitmq:install` | Publish config and print env examples. | No |
| `rabbitmq:check` | Verify connection and optionally passively check a queue. | Yes |
| `rabbitmq:setup` | Declare configured topology and optionally one queue-driver queue. | Yes, when declaration is expected. |
| `rabbitmq:stats` | Show queue metrics through the Laravel queue driver. | Yes |
| `rabbitmq:management` | Inspect broker overview, queues, or one queue through the Management API. | Yes |
| `rabbitmq:doctor` | Run config, security, connection, Management API, and optional round-trip checks. | Yes |
| `rabbitmq:consume-test` | Publish and consume a round-trip test message. | Yes, with a test queue. |
| `rabbitmq:purge` | Purge a queue. | No |

### `rabbitmq:doctor`

```bash
php artisan rabbitmq:doctor
php artisan rabbitmq:doctor default --queue=laravel-rabbit.doctor
php artisan rabbitmq:doctor --skip-roundtrip
```

Checks:

- connection configuration exists
- security policy passes
- `QUEUE_CONNECTION` is `rabbitmq`
- Laravel queue driver is registered
- AMQP channel can open
- Management API is reachable when enabled
- optional publish/consume round trip succeeds

### `rabbitmq:management`

```bash
php artisan rabbitmq:management
php artisan rabbitmq:management --queue=orders
php artisan rabbitmq:management --queues
php artisan rabbitmq:management --vhost=/
```

| Mode | Output |
| --- | --- |
| default | Cluster name, RabbitMQ version, object totals, and message totals. |
| `--queue=orders` | Queue state, ready messages, reserved messages, total messages, consumers, memory, and idle time. |
| `--queues` | Queue list with ready, reserved, total, consumers, and state. |

### `rabbitmq:stats`

```bash
php artisan rabbitmq:stats orders
```

When the Management API is enabled, this command shows real broker metrics. Without it, RabbitMQ's AMQP queue declaration only provides total ready depth, so reserved and delayed counts are not available.

### Other Commands

| Command | Example |
| --- | --- |
| Connectivity check | `php artisan rabbitmq:check default --queue=orders` |
| Topology setup | `php artisan rabbitmq:setup default --queue=orders` |
| Consume test | `php artisan rabbitmq:consume-test default --queue=healthcheck` |
| Purge queue | `php artisan rabbitmq:purge orders --force` |

## Low-Level AMQP Usage

Use the facade when you need direct AMQP behavior outside Laravel's queue worker.

### Publish Text

```php
use Pushin\LaravelRabbit\Facades\LaravelRabbit;

LaravelRabbit::publish(
    body: 'order created',
    routingKey: 'orders.created',
    exchange: 'events',
);
```

### Publish JSON

```php
use Illuminate\Support\Str;
use Pushin\LaravelRabbit\Facades\LaravelRabbit;

LaravelRabbit::publishJson(
    payload: ['order_id' => 123],
    routingKey: 'orders.created',
    exchange: 'events',
    properties: [
        'correlation_id' => (string) Str::uuid(),
        'headers' => [
            'tenant' => 'pushin',
        ],
    ],
);
```

### Consume Messages

```php
use PhpAmqpLib\Message\AMQPMessage;
use Pushin\LaravelRabbit\Facades\LaravelRabbit;

LaravelRabbit::consume(function (AMQPMessage $message): void {
    $payload = json_decode($message->getBody(), true, flags: JSON_THROW_ON_ERROR);

    // Process the message.
});
```

By default, a message is acknowledged when the callback finishes without errors. If the callback returns `false`, the message is nacked.

```php
use PhpAmqpLib\Message\AMQPMessage;
use Pushin\LaravelRabbit\Facades\LaravelRabbit;
use Pushin\LaravelRabbit\ValueObjects\ConsumerResult;

LaravelRabbit::consume(function (AMQPMessage $message): ConsumerResult {
    return ConsumerResult::nack(requeue: true);
});
```

### Consume Options

| Option | Default | Description |
| --- | --- | --- |
| `tag` | `''` | Consumer tag. |
| `no_ack` | `false` | When true, RabbitMQ auto-acknowledges deliveries. |
| `exclusive` | `false` | Exclusive consumer. |
| `wait_timeout` | `1.0` | Wait timeout per consume loop. |
| `idle_timeout` | `null` | Stop after idle timeout. |
| `max_messages` | `null` | Stop after consuming this many messages. |
| `stop_when_empty` | `false` | Stop after the queue is empty. |
| `ack_on_success` | `true` | Ack when callback succeeds. |
| `nack_on_false` | `true` | Nack when callback returns `false`. |
| `nack_on_false_requeue` | `false` | Requeue when callback returns `false`. |
| `reject_on_exception` | `true` | Reject when callback throws. |
| `reject_on_exception_requeue` | `false` | Requeue when callback throws. |

```php
LaravelRabbit::consume($callback, options: [
    'tag' => 'orders-worker-1',
    'wait_timeout' => 1.0,
    'idle_timeout' => 30.0,
    'max_messages' => 100,
]);
```

### Get One Message

```php
$message = LaravelRabbit::get(queue: 'orders');

if ($message !== null) {
    // Process and manually acknowledge when noAck=false.
    $message->ack();
}
```

### Use a Specific Connection

```php
LaravelRabbit::connection('analytics')->publishJson(
    payload: ['event' => 'checkout'],
    routingKey: 'analytics.checkout',
);
```

Facade shortcut:

```php
LaravelRabbit::publishJson(
    payload: ['event' => 'checkout'],
    routingKey: 'analytics.checkout',
    connection: 'analytics',
);
```

## Topology

The package can automatically declare exchanges, queues, and bindings when a channel is opened.

```php
'topology' => [
    'auto_declare' => true,
    'exchanges' => [
        'events' => [
            'type' => 'topic',
            'durable' => true,
            'auto_delete' => false,
        ],
    ],
    'queues' => [
        'orders' => [
            'durable' => true,
            'arguments' => [
                'x-dead-letter-exchange' => 'events.dlx',
                'x-message-ttl' => 60000,
            ],
        ],
    ],
    'bindings' => [
        [
            'queue' => 'orders',
            'exchange' => 'events',
            'routing_key' => 'orders.*',
        ],
    ],
],
```

Manual declarations are also supported:

```php
$rabbit = LaravelRabbit::connection();

$rabbit->declareExchange('events', ['type' => 'topic', 'durable' => true]);
$rabbit->declareQueue('orders', ['durable' => true]);
$rabbit->bindQueue('orders', 'events', 'orders.*');
```

## Production Options

### Publisher Confirms

Publisher confirms are enabled by default.

```php
'publisher_confirms' => [
    'enabled' => true,
    'wait' => true,
    'timeout' => 5.0,
    'wait_for_returns' => false,
],
```

To handle returned messages, publish with `mandatory=true` and register a return listener.

```php
use PhpAmqpLib\Message\AMQPMessage;

LaravelRabbit::connection()
    ->onReturned(function ($replyCode, $replyText, $exchange, $routingKey, AMQPMessage $message): void {
        report("RabbitMQ returned message: {$replyCode} {$replyText}");
    })
    ->publish('payload', options: [
        'mandatory' => true,
        'wait_for_returns' => true,
    ]);
```

### QoS / Prefetch

```php
'qos' => [
    'enabled' => true,
    'prefetch_size' => 0,
    'prefetch_count' => 10,
    'global' => false,
],
```

```php
LaravelRabbit::connection()->qos(prefetchCount: 25);
```

### Host Failover

```php
'connections' => [
    'default' => [
        'user' => env('RABBITMQ_USER'),
        'password' => env('RABBITMQ_PASSWORD'),
        'vhost' => '/',
        'hosts' => [
            ['host' => 'rabbit-a.internal', 'port' => 5671],
            ['host' => 'rabbit-b.internal', 'port' => 5671],
        ],
        'ssl' => [
            'enabled' => true,
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
        'reconnect' => [
            'attempts' => 3,
            'sleep_ms' => 250,
            'multiplier' => 1.5,
        ],
    ],
],
```

<details>
<summary>Connection options</summary>

| Option | Description |
| --- | --- |
| `host`, `port`, `vhost`, `user`, `password` | RabbitMQ connection target and credentials. |
| `hosts` | Host list for failover. Host entries inherit root options and may override them. |
| `connection_name` | Name shown in RabbitMQ connection metadata. |
| `io_type` | `stream` or `socket`. |
| `lazy` | Defer connection creation until first use. |
| `insist` | AMQP insist flag. |
| `login_method` | `AMQPLAIN`, `PLAIN`, or `EXTERNAL`. |
| `login_response` | Optional login response. |
| `locale` | AMQP locale, usually `en_US`. |
| `connection_timeout` | TCP connection timeout. |
| `read_timeout` | Socket read timeout. |
| `write_timeout` | Socket write timeout. |
| `channel_rpc_timeout` | Channel RPC timeout. |
| `heartbeat` | RabbitMQ heartbeat interval. |
| `keepalive` | TCP keepalive flag. |
| `send_buffer_size` | Optional send buffer size. |
| `dispatch_signals` | Whether php-amqplib dispatches signals. |
| `protocol_strict_fields` | Strict AMQP field validation. |
| `debug_packets` | Packet debug flag. |

</details>

## Security

### Queue Payload Signing

Queue payload signing is an optional defense against job injection. When enabled, the driver adds an HMAC signature to every Laravel queue payload it publishes. During `queue:work`, messages without a valid signature are rejected before Laravel attempts to execute the job.

```dotenv
RABBITMQ_QUEUE_SIGN_PAYLOADS=true
RABBITMQ_QUEUE_SIGNING_KEY="${APP_KEY}"
RABBITMQ_QUEUE_INVALID_SIGNATURE_REQUEUE=false
```

| Env | Default | Description |
| --- | --- | --- |
| `RABBITMQ_QUEUE_SIGN_PAYLOADS` | `false` | Adds an HMAC signature to new queue payloads. |
| `RABBITMQ_QUEUE_VERIFY_PAYLOAD_SIGNATURES` | same as `RABBITMQ_QUEUE_SIGN_PAYLOADS` | Verifies signatures before returning a job to Laravel's worker. |
| `RABBITMQ_QUEUE_SIGNING_KEY` | `APP_KEY` | Secret key used for HMAC signing. Use the same value across all app instances that publish or consume these jobs. |
| `RABBITMQ_QUEUE_INVALID_SIGNATURE_REQUEUE` | `false` | When false, invalid messages are nacked without requeue to avoid poison-message loops. |

> [!NOTE]
> Payload signing does not replace broker security. Keep RabbitMQ credentials scoped per app, restrict vhost permissions, use TLS outside local development, and do not allow untrusted systems to publish into Laravel worker queues.

### TLS

```dotenv
RABBITMQ_SSL=true
RABBITMQ_PORT=5671
RABBITMQ_SSL_CAFILE=/etc/ssl/certs/ca.pem
RABBITMQ_SSL_VERIFY_PEER=true
RABBITMQ_SSL_VERIFY_PEER_NAME=true
```

```php
'ssl' => [
    'enabled' => true,
    'cafile' => '/path/ca.pem',
    'capath' => null,
    'local_cert' => '/path/client.pem',
    'local_pk' => '/path/client.key',
    'passphrase' => env('RABBITMQ_SSL_PASSPHRASE'),
    'verify_peer' => true,
    'verify_peer_name' => true,
    'ciphers' => null,
    'security_level' => null,
    'crypto_method' => 'STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT',
],
```

For security, `verify_peer=false` or `verify_peer_name=false` throws an exception unless `security.allow_insecure_tls=true`.

### Security Guards

```php
'security' => [
    'require_tls' => false,
    'allow_insecure_tls' => false,
    'enforce_tls_peer_verification' => true,
    'forbid_guest_on_remote_hosts' => true,
    'max_message_size' => null,
],
```

| Guard | Default | Effect |
| --- | --- | --- |
| `require_tls` | `false` | Rejects non-TLS AMQP connections when enabled. |
| `allow_insecure_tls` | `false` | Required before disabling TLS peer verification. |
| `enforce_tls_peer_verification` | `true` | Requires peer and peer-name verification for TLS. |
| `forbid_guest_on_remote_hosts` | `true` | Rejects the `guest` user outside local hosts. |
| `max_message_size` | `null` | Rejects publishes larger than the configured byte limit. |

Recommendations:

- Use TLS outside local development.
- Do not use the `guest` user on remote hosts.
- Keep `verify_peer` and `verify_peer_name` enabled.
- Configure `max_message_size` when your application has a known payload limit.
- Prefer publisher confirms for important flows.

## Message Properties

Accepted properties follow `AMQPMessage`.

| Property | Description |
| --- | --- |
| `content_type` | MIME type of the payload. |
| `content_encoding` | Payload encoding. |
| `headers` / `application_headers` | Application headers. |
| `delivery_mode` | Use `2` for persistent messages. |
| `priority` | Message priority. |
| `correlation_id` | Correlation id for tracing or RPC. |
| `reply_to` | Reply queue. |
| `expiration` | Per-message TTL in milliseconds as a string. |
| `message_id` | Message id. |
| `timestamp` | Message timestamp. |
| `type` | Application message type. |
| `user_id` | User id. |
| `app_id` | Application id. |
| `cluster_id` | Cluster id. |

```php
LaravelRabbit::publishJson(
    ['order_id' => 123],
    properties: [
        'delivery_mode' => 2,
        'expiration' => '60000',
        'priority' => 5,
        'headers' => [
            'source' => 'checkout',
        ],
    ],
);
```

## Testing

```bash
composer test
composer test:unit
composer test:feature
```

| Test area | Covered |
| --- | --- |
| Publishing | Text, JSON, properties, confirms, and routing. |
| Consuming | Callback flow, ack, nack, reject, timeout, and round-trip tests. |
| Laravel queue | Dispatch, `onQueue`, delayed jobs, release, clear, and worker integration. |
| Management API | Metrics mapping, command output, TLS policy, and remote `guest` guard. |
| Security | Queue payload signing, tamper rejection, insecure TLS, remote `guest`, config sanitization, and max message size. |
| Topology | Exchanges, queues, bindings, queue arguments, and AMQP table conversion. |
