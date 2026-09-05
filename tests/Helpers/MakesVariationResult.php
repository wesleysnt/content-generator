<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\AI\DTO\VariationResult;

trait MakesVariationResult
{
    public function makeVariationResult(array $overrides = []): VariationResult
    {
        $data = array_merge([
            'title' => 'Cloud Accounting for Small Businesses',
            'slug' => 'cloud-accounting-small-businesses',
            'excerpt' => 'A practical guide.',
            'meta_title' => 'Cloud Accounting for Small Businesses',
            'meta_description' => 'A practical guide to cloud accounting for small business owners who want less admin work and more clarity about their finances.',
            'focus_keyword' => 'cloud accounting',
            'secondary_keywords' => ['online accounting'],
            'category' => 'Finance',
            'tags' => ['accounting'],
            'og_title' => 'Cloud Accounting for Small Businesses',
            'og_description' => 'A practical guide.',
            'faq' => [['question' => 'What is cloud accounting?', 'answer' => 'Accounting software hosted online.']],
            'schema_type' => 'Article',
            'sections' => [
                ['heading' => 'Introduction', 'body' => '<p>Cloud accounting intro.</p>'],
                ['heading' => 'Benefits', 'body' => '<p>Benefits text.</p>'],
            ],
        ], $overrides);

        return VariationResult::fromArray($data, 100, 500, 'deepseek-v4-pro');
    }
}
