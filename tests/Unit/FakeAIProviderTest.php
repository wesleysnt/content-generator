<?php

declare(strict_types=1);

use App\AI\Contracts\AIProvider;
use App\AI\DTO\VariationResult;
use App\AI\Fake\FakeAIProvider;

it('returns queued results in order', function () {
    $first = VariationResult::fromArray([
        'title' => 'First', 'slug' => 'first', 'excerpt' => 'E',
        'meta_title' => 'M', 'meta_description' => 'D', 'focus_keyword' => 'kw',
        'secondary_keywords' => [], 'category' => 'C', 'tags' => [],
        'og_title' => 'O', 'og_description' => 'OD', 'faq' => [],
        'schema_type' => 'Article', 'sections' => [['heading' => 'H', 'body' => '<p>B</p>']],
    ], 10, 20, 'deepseek-v4-pro');

    $provider = new FakeAIProvider([$first]);

    expect($provider)->toBeInstanceOf(AIProvider::class);
});
