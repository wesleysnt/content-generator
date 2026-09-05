<?php

declare(strict_types=1);

use App\AI\DeepSeek\DeepSeekProvider;
use App\AI\Exceptions\AICallException;
use App\AI\Exceptions\AIResponseException;
use App\AI\Exceptions\ValidationFailedException;

function loadAiFixture(string $name): array
{
    return json_decode(file_get_contents(__DIR__.'/../Fixtures/ai/'.$name), true);
}

it('parses a valid generation response', function () {
    $provider = app(DeepSeekProvider::class);

    $result = $provider->parseResponse(loadAiFixture('valid_generation_response.json'), 'deepseek-v4-pro');

    expect($result->title)->toBe('Cloud Accounting for Small Businesses');
    expect($result->sections)->toHaveCount(2);
    expect($result->inputTokens)->toBe(120);
    expect($result->outputTokens)->toBe(800);
});

it('throws ValidationFailedException on missing fields', function () {
    $provider = app(DeepSeekProvider::class);

    expect(fn () => $provider->parseResponse(loadAiFixture('missing_field_response.json'), 'deepseek-v4-pro'))
        ->toThrow(ValidationFailedException::class);
});

it('throws AIResponseException on malformed JSON', function () {
    $provider = app(DeepSeekProvider::class);

    expect(fn () => $provider->parseResponse(loadAiFixture('malformed_json_response.json'), 'deepseek-v4-pro'))
        ->toThrow(AIResponseException::class, 'Response is not valid JSON');
});

it('throws AICallException on truncated response', function () {
    $provider = app(DeepSeekProvider::class);

    expect(fn () => $provider->parseResponse(loadAiFixture('truncated_response.json'), 'deepseek-v4-pro'))
        ->toThrow(AICallException::class, 'Generation truncated');
});

it('throws AICallException on empty content', function () {
    $provider = app(DeepSeekProvider::class);

    expect(fn () => $provider->parseResponse(loadAiFixture('empty_content_response.json'), 'deepseek-v4-pro'))
        ->toThrow(AICallException::class, 'Empty content');
});
