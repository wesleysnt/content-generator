<?php

declare(strict_types=1);

namespace App\AI\DTO;

final readonly class SectionResult
{
    public function __construct(
        public string $heading,
        public string $body,
        public int $inputTokens,
        public int $outputTokens,
        public string $model,
        public array $raw,
    ) {}
}
