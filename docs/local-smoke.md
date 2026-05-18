# Local Laravel Smoke App

The repository ignores `.playground/`, which can be used for a disposable Laravel app that installs this package through a Composer path repository.

## Create the App

```bash
mkdir -p .playground
composer create-project laravel/laravel .playground/laravel-rabbit-smoke
cd .playground/laravel-rabbit-smoke
composer config repositories.pushin-laravel-rabbit '{"type":"path","url":"../..","options":{"symlink":true}}'
composer require "pushin/laravel-rabbit:*@dev" -W
```

## Configure `.env`

```dotenv
QUEUE_CONNECTION=rabbitmq
LARAVEL_RABBIT_CONNECTION=default
RABBITMQ_HOST=127.0.0.1
RABBITMQ_PORT=5672
RABBITMQ_VHOST=/
RABBITMQ_USER=your-user
RABBITMQ_PASSWORD=your-password
RABBITMQ_QUEUE=default
RABBITMQ_EXCHANGE=
RABBITMQ_ROUTING_KEY=
RABBITMQ_TOPOLOGY_AUTO_DECLARE=false
RABBITMQ_MANAGEMENT_ENABLED=true
RABBITMQ_MANAGEMENT_HOST=127.0.0.1
RABBITMQ_MANAGEMENT_PORT=15672
RABBITMQ_MANAGEMENT_USER=your-user
RABBITMQ_MANAGEMENT_PASSWORD=your-password
```

## Smoke Commands

```bash
php artisan rabbitmq:doctor --queue=laravel-rabbit-doctor
php artisan rabbitmq:consume-test --queue=laravel-rabbit-smoke
php artisan rabbitmq:setup --queue=orders
php artisan rabbitmq:check --queue=orders
php artisan rabbitmq:stats orders
php artisan rabbitmq:management --queue=orders
```

## Queue Worker Smoke Test

Create a job:

```bash
php artisan make:job RabbitSmokeJob
```

Make its `handle()` write to `storage/app/rabbit-smoke.log`, then dispatch and process it:

```bash
php artisan tinker --execute="App\\Jobs\\RabbitSmokeJob::dispatch('smoke-'.time())->onQueue('orders');"
php artisan queue:work --queue=orders --once -vvv
```

Delayed jobs can be smoke-tested with:

```bash
php artisan tinker --execute="App\\Jobs\\RabbitSmokeJob::dispatch('delayed-'.time())->onQueue('orders')->delay(now()->addSeconds(2));"
sleep 3
php artisan queue:work --queue=orders --once -vvv
```
