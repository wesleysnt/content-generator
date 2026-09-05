<?php

declare(strict_types=1);

namespace App\AI\DTO;

final readonly class SectionRegenerationRequest
{
    public function __construct(
        public string $topic,
        public string $primaryKeyword,
        public string $tone,
        public ?string $targetPersona,
        public string $contextSections,
        public string $heading,
        public string $currentBody,
        public string $promptContent,
        public string $model,
        public int $maxTokens,
    ) {}
}
