<?php

declare(strict_types=1);

namespace App\AI\DTO;

final readonly class VariationResult
{
    private function __construct(
        public string $title,
        public string $slug,
        public string $excerpt,
        public string $metaTitle,
        public string $metaDescription,
        public string $focusKeyword,
        public array $secondaryKeywords,
        public string $category,
        public array $tags,
        public string $ogTitle,
        public string $ogDescription,
        public array $faq,
        public string $schemaType,
        public array $sections,
        public int $inputTokens,
        public int $outputTokens,
        public string $model,
        public array $raw,
    ) {}

    public static function fromArray(array $data, int $inputTokens, int $outputTokens, string $model): self
    {
        return new self(
            title: $data['title'],
            slug: $data['slug'],
            excerpt: $data['excerpt'],
            metaTitle: $data['meta_title'],
            metaDescription: $data['meta_description'],
            focusKeyword: $data['focus_keyword'],
            secondaryKeywords: $data['secondary_keywords'],
            category: $data['category'],
            tags: $data['tags'],
            ogTitle: $data['og_title'],
            ogDescription: $data['og_description'],
            faq: $data['faq'],
            schemaType: $data['schema_type'],
            sections: $data['sections'],
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            model: $model,
            raw: $data,
        );
    }
}
