<?php

declare(strict_types=1);

use App\AI\Exceptions\ValidationFailedException;
use App\AI\Validation\GenerationValidator;

function validGenerationData(): array
{
    return [
        'title' => 'Cloud Accounting for SMBs',
        'slug' => 'cloud-accounting-for-smbs',
        'excerpt' => 'Short excerpt.',
        'meta_title' => 'Cloud Accounting for Small Businesses',
        'meta_description' => 'A practical guide to cloud accounting for small business owners who want less admin work and more clarity.',
        'focus_keyword' => 'cloud accounting',
        'secondary_keywords' => ['online accounting'],
        'category' => 'Finance',
        'tags' => ['accounting'],
        'og_title' => 'Cloud Accounting for Small Businesses',
        'og_description' => 'A practical guide.',
        'faq' => [['question' => 'What is cloud accounting?', 'answer' => 'It is accounting software hosted online.']],
        'schema_type' => 'Article',
        'sections' => [
            ['heading' => 'Introduction', 'body' => '<p>Cloud accounting intro.</p>'],
            ['heading' => 'Benefits', 'body' => '<p>Benefits text.</p>'],
        ],
    ];
}

it('passes valid data', function () {
    $validator = new GenerationValidator;

    expect(fn () => $validator->validate(validGenerationData()))->not->toThrow(ValidationFailedException::class);
});

it('fails when title is missing', function () {
    $data = validGenerationData();
    unset($data['title']);

    $validator = new GenerationValidator;

    expect(fn () => $validator->validate($data))
        ->toThrow(ValidationFailedException::class, 'The title field is required.');
});

it('fails when sections is empty', function () {
    $data = validGenerationData();
    $data['sections'] = [];

    $validator = new GenerationValidator;

    expect(fn () => $validator->validate($data))->toThrow(ValidationFailedException::class);
});

it('warns when meta title is too long', function () {
    $data = validGenerationData();
    $data['meta_title'] = str_repeat('a', 80);

    $warnings = (new GenerationValidator)->warnings($data, 1500);

    expect($warnings)->toContain('meta_title exceeds 60 characters (80)');
});

it('warns when focus keyword is missing from title and body', function () {
    $data = validGenerationData();
    $data['focus_keyword'] = 'quantum flux capacitors';
    $data['sections'] = [['heading' => 'H', 'body' => '<p>No keyword here.</p>']];

    $warnings = (new GenerationValidator)->warnings($data, 1500);

    expect($warnings)->toContain('focus keyword not found in title or body');
});

it('warns when word count is far from target', function () {
    $data = validGenerationData();

    $warnings = (new GenerationValidator)->warnings($data, 1500);

    expect($warnings)->toContain('word count 9 is below target 1500 (tolerance 30%)');
});

it('labels an over-long article as exceeding the target, not below it', function () {
    $data = validGenerationData();
    $data['sections'] = [['heading' => 'H', 'body' => '<p>'.str_repeat('word ', 700).'</p>']];

    $warnings = (new GenerationValidator)->warnings($data, 500);

    expect($warnings)->toContain('word count 704 exceeds target 500 (tolerance 30%)');
    expect($warnings)->not->toContain('below target');
});
