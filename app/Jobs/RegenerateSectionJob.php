<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\GenerationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RegenerateSectionJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 30, 120];

    public function __construct(public int $variationId, public int $sectionId) {}

    public function uniqueId(): string
    {
        return "section-{$this->variationId}-{$this->sectionId}";
    }

    public function handle(GenerationService $service): void
    {
        $service->regenerateSection($this->variationId, $this->sectionId);
    }
}
