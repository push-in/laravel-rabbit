# Changelog

All notable changes to `pushinbr/laravel-rabbit` will be documented in this file.

## [0.1.2] - 2026-05-18

### Changed

- Added Packagist metadata, including homepage, keywords, author, and support links.
- Added a Composer branch alias for the next `dev-main` development cycle.

## [0.1.1] - 2026-05-18

### Fixed

- Added compatibility for older Orchestra Testbench releases in the package test suite.
- Updated the GitHub Actions checkout action to the current Node 24 compatible major version.

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
[0.1.1]: https://github.com/push-in/laravel-rabbit/releases/tag/v0.1.1
[0.1.2]: https://github.com/push-in/laravel-rabbit/releases/tag/v0.1.2
