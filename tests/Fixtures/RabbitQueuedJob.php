<?php

namespace Pushin\LaravelRabbit\Tests\Fixtures;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class RabbitQueuedJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public function handle(): void
    {
    }
}
