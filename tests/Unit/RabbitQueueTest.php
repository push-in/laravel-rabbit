<?php

namespace Pushin\LaravelRabbit\Tests\Unit;

use Illuminate\Container\Container;
use Mockery;
use OutOfBoundsException;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Pushin\LaravelRabbit\Queue\Jobs\RabbitJob;
use Pushin\LaravelRabbit\Queue\RabbitQueue;
use Pushin\LaravelRabbit\RabbitManager;
use Pushin\LaravelRabbit\Tests\Fakes\FakeAmqpChannel;
use Pushin\LaravelRabbit\Tests\Fakes\FakeAmqpConnection;
use Pushin\LaravelRabbit\Tests\Fakes\FakeAmqpFactory;
use Pushin\LaravelRabbit\Tests\Fakes\FakeDeliveredMessage;
use Pushin\LaravelRabbit\Tests\Fakes\FakeManagementClient;
use PHPUnit\Framework\TestCase;

class RabbitQueueTest extends TestCase
{
    public function test_it_pushes_laravel_jobs_to_the_selected_queue(): void
    {
        $channel = new FakeAmqpChannel();
        $queue = $this->queue($channel, ['exchange' => 'jobs']);

        $jobId = $queue->push('Handler@handle', ['id' => 10], 'emails');

        $this->assertNotSame('', $jobId);
        $this->assertSame('jobs', $channel->exchangeDeclarations[0]['exchange']);
        $this->assertSame('emails', $channel->queueDeclarations[0]['queue']);
        $this->assertSame('emails', $channel->bindings[0]['routingKey']);
        $this->assertSame('jobs', $channel->published[0]['exchange']);
        $this->assertSame('emails', $channel->published[0]['routingKey']);

        $payload = json_decode($channel->published[0]['message']->getBody(), true);
        $this->assertSame('Handler@handle', $payload['job']);
        $this->assertSame(['id' => 10], $payload['data']);

        $headers = $channel->published[0]['message']->get('application_headers')->getNativeData();
        $this->assertSame('emails', $headers['laravel-queue']);
        $this->assertSame(0, $headers['laravel-attempts']);
    }

    public function test_it_schedules_delayed_jobs_with_ttl_dead_letter_queues_by_default(): void
    {
        $channel = new FakeAmqpChannel();
        $queue = $this->queue($channel, ['exchange' => 'jobs']);

        $queue->later(60, 'Handler@handle', ['id' => 10], 'emails');

        $this->assertSame('emails', $channel->queueDeclarations[0]['queue']);
        $this->assertSame('laravel.delay.emails.60', $channel->queueDeclarations[1]['queue']);
        $this->assertSame(60000, $channel->queueDeclarations[1]['arguments']['x-message-ttl']);
        $this->assertSame('jobs', $channel->queueDeclarations[1]['arguments']['x-dead-letter-exchange']);
        $this->assertSame('emails', $channel->queueDeclarations[1]['arguments']['x-dead-letter-routing-key']);
        $this->assertSame('', $channel->published[0]['exchange']);
        $this->assertSame('laravel.delay.emails.60', $channel->published[0]['routingKey']);
    }

    public function test_it_pops_rabbit_messages_as_laravel_jobs(): void
    {
        $channel = new FakeAmqpChannel();
        $message = Mockery::mock(AMQPMessage::class);
        $message->shouldReceive('getBody')->andReturn('{"uuid":"job-1","job":"Handler@handle","data":[]}');
        $message->shouldReceive('ack')->once()->withNoArgs();
        $channel->getMessage = $message;
        $queue = $this->queue($channel);

        $job = $queue->pop('emails');

        $this->assertInstanceOf(RabbitJob::class, $job);
        $this->assertSame('{"uuid":"job-1","job":"Handler@handle","data":[]}', $job->getRawBody());

        $job->delete();
    }

    public function test_releasing_a_job_republishes_it_with_incremented_attempts_and_acks_original_message(): void
    {
        $channel = new FakeAmqpChannel();
        $message = Mockery::mock(AMQPMessage::class);
        $message->shouldReceive('getBody')->andReturn('{"uuid":"job-1","job":"Handler@handle","data":[]}');
        $message->shouldReceive('get')->with('application_headers')->andReturn(new AMQPTable(['laravel-attempts' => 0]));
        $message->shouldReceive('ack')->once()->withNoArgs();
        $channel->getMessage = $message;
        $queue = $this->queue($channel);

        $job = $queue->pop('emails');
        $this->assertSame(1, $job->attempts());

        $job->release(30);

        $headers = $channel->published[0]['message']->get('application_headers')->getNativeData();
        $this->assertSame(1, $headers['laravel-attempts']);
        $this->assertSame('laravel.delay.emails.30', $channel->published[0]['routingKey']);
    }

    public function test_it_clears_a_queue(): void
    {
        $channel = new FakeAmqpChannel();
        $queue = $this->queue($channel);

        $this->assertSame(0, $queue->clear('emails'));
        $this->assertSame('emails', $channel->purges[0]['queue']);
    }

    public function test_it_reports_real_queue_metrics_from_the_management_api(): void
    {
        $channel = new FakeAmqpChannel();
        $management = new FakeManagementClient(queueStats: [
            "/\0orders" => [
                'messages' => 7,
                'messages_ready' => 4,
                'messages_unacknowledged' => 3,
            ],
        ], queueLists: [
            '/' => [
                ['name' => 'orders', 'messages' => 7],
                ['name' => 'laravel.delay.orders.30', 'messages' => 2],
                ['name' => 'laravel.delay.orders.60', 'messages' => 5],
                ['name' => 'laravel.delay.emails.60', 'messages' => 9],
            ],
        ]);
        $queue = $this->queue($channel, management: $management);

        $this->assertTrue($queue->managementApiEnabled());
        $this->assertSame(7, $queue->size('orders'));
        $this->assertSame(4, $queue->pendingSize('orders'));
        $this->assertSame(3, $queue->reservedSize('orders'));
        $this->assertSame(7, $queue->delayedSize('orders'));
        $this->assertSame([], $channel->queueDeclarations);
    }

    public function test_it_signs_queue_payloads_when_enabled(): void
    {
        $channel = new FakeAmqpChannel();
        $queue = $this->queue($channel, [
            'security' => [
                'sign_payloads' => true,
                'verify_payload_signatures' => true,
                'signing_key' => 'test-signing-key',
            ],
        ]);

        $queue->pushRaw('{"uuid":"job-1"}', 'emails');

        $message = $channel->published[0]['message'];
        $headers = $message->get('application_headers')->getNativeData();

        $this->assertSame('hmac-sha256', $headers['laravel-signature-algorithm']);
        $this->assertSame(
            hash_hmac('sha256', "emails\n" . '{"uuid":"job-1"}', 'test-signing-key'),
            $headers['laravel-signature'],
        );
    }

    public function test_it_accepts_signed_queue_payloads_when_verification_is_enabled(): void
    {
        $channel = new FakeAmqpChannel();
        $payload = '{"uuid":"job-1","job":"Handler@handle","data":[]}';
        $channel->getMessage = new FakeDeliveredMessage($payload, [
            'application_headers' => new AMQPTable([
                'laravel-signature' => hash_hmac('sha256', "emails\n{$payload}", 'test-signing-key'),
                'laravel-signature-algorithm' => 'hmac-sha256',
            ]),
        ]);
        $queue = $this->queue($channel, [
            'security' => [
                'verify_payload_signatures' => true,
                'signing_key' => 'test-signing-key',
            ],
        ]);

        $job = $queue->pop('emails');

        $this->assertInstanceOf(RabbitJob::class, $job);
    }

    public function test_it_rejects_unsigned_queue_payloads_when_verification_is_enabled(): void
    {
        $channel = new FakeAmqpChannel();
        $message = new FakeDeliveredMessage('{"uuid":"job-1","job":"Handler@handle","data":[]}');
        $channel->getMessage = $message;
        $queue = $this->queue($channel, [
            'security' => [
                'verify_payload_signatures' => true,
                'signing_key' => 'test-signing-key',
            ],
        ]);

        $this->assertNull($queue->pop('emails'));
        $this->assertTrue($message->nacked);
        $this->assertFalse($message->requeued);
    }

    public function test_it_rejects_tampered_queue_payloads_when_verification_is_enabled(): void
    {
        $channel = new FakeAmqpChannel();
        $payload = '{"uuid":"job-1","job":"Handler@handle","data":[]}';
        $message = new FakeDeliveredMessage($payload, [
            'application_headers' => new AMQPTable([
                'laravel-signature' => hash_hmac('sha256', "emails\n" . '{"uuid":"other"}', 'test-signing-key'),
                'laravel-signature-algorithm' => 'hmac-sha256',
            ]),
        ]);
        $channel->getMessage = $message;
        $queue = $this->queue($channel, [
            'security' => [
                'verify_payload_signatures' => true,
                'signing_key' => 'test-signing-key',
            ],
        ]);

        $this->assertNull($queue->pop('emails'));
        $this->assertTrue($message->nacked);
        $this->assertFalse($message->requeued);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $config
     */
    private function queue(FakeAmqpChannel $channel, array $config = [], ?FakeManagementClient $management = null): RabbitQueue
    {
        $manager = new RabbitManager([
            'connection' => 'default',
            'connections' => [
                'default' => [
                    'vhost' => '/',
                    'topology' => ['auto_declare' => false],
                    'qos' => ['enabled' => false],
                    'publisher_confirms' => ['enabled' => false],
                ],
            ],
        ], new FakeAmqpFactory(new FakeAmqpConnection($channel)));

        $queue = new RabbitQueue($manager, array_replace_recursive([
            'rabbit_connection' => 'default',
            'queue' => 'default',
            'exchange' => '',
            'exchange_type' => 'direct',
            'routing_key' => null,
            'declare' => true,
            'durable' => true,
            'exclusive' => false,
            'auto_delete' => false,
            'delivery_mode' => 2,
            'security' => [
                'sign_payloads' => false,
                'verify_payload_signatures' => false,
                'signing_key' => 'test-signing-key',
                'invalid_signature_requeue' => false,
            ],
            'delay' => [
                'strategy' => 'ttl',
                'queue_prefix' => 'laravel.delay.',
            ],
        ], $config), $management);

        $queue->setContainer(new Container());
        $queue->setConnectionName('rabbitmq');

        return $queue;
    }
}
