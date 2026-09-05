<?php

declare(strict_types=1);

namespace App\AI\DTO;

final readonly class TitleRegenerationRequest
{
    public function __construct(
        public string $topic,
        public string $primaryKeyword,
        public string $tone,
        public ?string $targetPersona,
        public string $currentTitle,
        public array $negativeContext,
        public string $promptContent,
        public string $model,
        public int $maxTokens,
    ) {}
}
