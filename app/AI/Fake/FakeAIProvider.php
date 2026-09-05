<?php

declare(strict_types=1);

namespace App\AI\Fake;

use App\AI\Contracts\AIProvider;
use App\AI\DTO\GenerationRequest;
use App\AI\DTO\SectionRegenerationRequest;
use App\AI\DTO\SectionResult;
use App\AI\DTO\TitleRegenerationRequest;
use App\AI\DTO\TitleResult;
use App\AI\DTO\VariationResult;

class FakeAIProvider implements AIProvider
{
    /** @var array<int, object> Queue of results or Throwables */
    private array $queue = [];

    public function __construct(array $queue = [])
    {
        $this->queue = array_values($queue);
    }

    private function next(): object
    {
        if (empty($this->queue)) {
            throw new \RuntimeException('FakeAIProvider queue is empty');
        }

        $item = array_shift($this->queue);

        if ($item instanceof \Throwable) {
            throw $item;
        }

        return $item;
    }

    public function generateVariation(GenerationRequest $request): VariationResult
    {
        /** @var VariationResult $result */
        $result = $this->next();

        return $result;
    }

    public function regenerateVariation(GenerationRequest $request): VariationResult
    {
        /** @var VariationResult $result */
        $result = $this->next();

        return $result;
    }

    public function regenerateSection(SectionRegenerationRequest $request): SectionResult
    {
        /** @var SectionResult $result */
        $result = $this->next();

        return $result;
    }

    public function regenerateTitle(TitleRegenerationRequest $request): TitleResult
    {
        /** @var TitleResult $result */
        $result = $this->next();

        return $result;
    }
}
