<?php

declare(strict_types=1);

namespace App\AI\Contracts;

use App\AI\DTO\GenerationRequest;
use App\AI\DTO\SectionRegenerationRequest;
use App\AI\DTO\SectionResult;
use App\AI\DTO\TitleRegenerationRequest;
use App\AI\DTO\TitleResult;
use App\AI\DTO\VariationResult;

interface AIProvider
{
    public function generateVariation(GenerationRequest $request): VariationResult;

    public function regenerateVariation(GenerationRequest $request): VariationResult;

    public function regenerateSection(SectionRegenerationRequest $request): SectionResult;

    public function regenerateTitle(TitleRegenerationRequest $request): TitleResult;
}
