<?php

declare(strict_types=1);

namespace App\AI\DTO;

use App\Enums\AngleType;

final readonly class GenerationRequest
{
    public function __construct(
        public string $topic,
        public string $primaryKeyword,
        public array $secondaryKeywords,
        public string $tone,
        public ?string $targetPersona,
        public int $targetWordCount,
        public ?string $additionalInstructions,
        public AngleType $angle,
        public array $negativeContext,
        public string $promptContent,
        public string $model,
        public int $maxTokens,
        public ?string $repairContext = null,
    ) {}
}
