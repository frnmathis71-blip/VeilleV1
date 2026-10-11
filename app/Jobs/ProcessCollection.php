<?php

namespace App\Jobs;

use App\Services\Collection\Collector;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessCollection implements ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public int $tries = 3;

    public function handle(Collector $collector): void
    {
        $collector->process();
    }
}
