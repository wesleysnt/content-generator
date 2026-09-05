<?php

declare(strict_types=1);

namespace App\AI\DTO;

final readonly class TitleResult
{
    public function __construct(
        public string $title,
        public string $slug,
        public string $metaTitle,
        public string $metaDescription,
        public int $inputTokens,
        public int $outputTokens,
        public string $model,
        public array $raw,
    ) {}
}
