<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\GenerationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateContentJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 30, 120];

    public function __construct(public int $variationId) {}

    public function uniqueId(): int
    {
        return $this->variationId;
    }

    public function handle(GenerationService $service): void
    {
        $service->generateVariation($this->variationId);
    }
}
