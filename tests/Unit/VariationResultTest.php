<?php

declare(strict_types=1);

use App\AI\DTO\VariationResult;

it('builds a VariationResult from an array', function () {
    $data = [
        'title' => 'T', 'slug' => 't', 'excerpt' => 'E',
        'meta_title' => 'MT', 'meta_description' => 'MD',
        'focus_keyword' => 'kw', 'secondary_keywords' => ['kw2'],
        'category' => 'Tech', 'tags' => ['a'],
        'og_title' => 'OG', 'og_description' => 'OGD',
        'faq' => [['question' => 'Q', 'answer' => 'A']],
        'schema_type' => 'Article',
        'sections' => [['heading' => 'H', 'body' => '<p>B</p>']],
    ];

    $result = VariationResult::fromArray($data, 100, 200, 'deepseek-v4-pro');

    expect($result->title)->toBe('T');
    expect($result->sections)->toHaveCount(1);
    expect($result->faq[0]['question'])->toBe('Q');
    expect($result->inputTokens)->toBe(100);
    expect($result->raw)->toBe($data);
});
