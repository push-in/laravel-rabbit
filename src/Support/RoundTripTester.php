<?php

namespace Pushin\LaravelRabbit\Support;

use JsonException;
use PhpAmqpLib\Message\AMQPMessage;
use Pushin\LaravelRabbit\RabbitConnection;
use Throwable;

class RoundTripTester
{
    /**
     * @param array<string, mixed> $options
     */
    public function run(RabbitConnection $connection, string $queue, array $options = []): RoundTripResult
    {
        $timeout = max(1, (float) ($options['timeout'] ?? 5.0));
        $declare = (bool) ($options['declare'] ?? true);
        $messageId = (string) ($options['message_id'] ?? bin2hex(random_bytes(16)));
        $channel = $connection->channel(prepare: false);

        if ($declare) {
            $channel->queueDeclare(
                $queue,
                false,
                (bool) ($options['durable'] ?? false),
                false,
                (bool) ($options['auto_delete'] ?? true),
                false,
                (array) ($options['arguments'] ?? []),
            );
        }

        $payload = json_encode([
            'id' => $messageId,
            'source' => 'laravel-rabbit',
            'created_at' => time(),
        ], JSON_THROW_ON_ERROR);

        $connection->publish(
            $payload,
            $queue,
            '',
            [
                'content_type' => 'application/json',
                'message_id' => $messageId,
                'headers' => [
                    'laravel-rabbit-test-id' => $messageId,
                ],
            ],
            [
                'prepare_channel' => false,
                'confirm_select' => true,
                'mandatory' => (bool) ($options['mandatory'] ?? false),
            ],
        );

        $deadline = microtime(true) + $timeout;

        do {
            $message = $channel->basicGet($queue, false);

            if (! $message instanceof AMQPMessage) {
                usleep(100000);
                continue;
            }

            if ($this->messageMatches($message, $messageId)) {
                $message->ack();

                return new RoundTripResult(true, sprintf('Round-trip message [%s] was published and consumed.', $messageId), $messageId);
            }

            $message->nack(true);

            return new RoundTripResult(false, 'Received a message, but it was not the round-trip test message.', $messageId);
        } while (microtime(true) < $deadline);

        return new RoundTripResult(false, sprintf('Timed out waiting for round-trip message [%s].', $messageId), $messageId);
    }

    private function messageMatches(AMQPMessage $message, string $messageId): bool
    {
        try {
            $payload = json_decode($message->getBody(), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }

        if (is_array($payload) && ($payload['id'] ?? null) === $messageId) {
            return true;
        }

        try {
            return $message->get('message_id') === $messageId;
        } catch (Throwable) {
            return false;
        }
    }
}
