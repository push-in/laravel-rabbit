# Changelog

All notable changes to `pushinbr/laravel-rabbit` will be documented in this file.

## [0.1.0] - 2026-05-18

### Added

- Initial Laravel Rabbit release.
- RabbitMQ queue driver for Laravel's native queue worker flow.
- AMQP publishing and consuming helpers using `php-amqplib`.
- Topology declarations for exchanges, queues, bindings, and arguments.
- Delayed job support using TTL dead-letter queues and optional delayed exchanges.
- RabbitMQ Management HTTP API client and Artisan operations commands.
- TLS, publisher confirms, QoS, host failover, reconnect, payload signing, and security guards.
- PHPUnit test suite for unit, feature, service provider, and queue integration behavior.

[0.1.0]: https://github.com/push-in/laravel-rabbit/releases/tag/v0.1.0
