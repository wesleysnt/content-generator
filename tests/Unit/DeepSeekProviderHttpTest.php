<?php

declare(strict_types=1);

use App\AI\DeepSeek\DeepSeekProvider;
use App\AI\DTO\GenerationRequest;
use App\AI\Validation\GenerationValidator;
use App\AI\Validation\SectionValidator;
use App\AI\Validation\TitleValidator;
use App\Enums\AngleType;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

function fullGenerationPayload(): string
{
    $raw = [
        'title' => 'Cloud Accounting for Small Businesses',
        'slug' => 'cloud-accounting-small-businesses',
        'excerpt' => 'A practical guide.',
        'meta_title' => 'Cloud Accounting for Small Businesses',
        'meta_description' => 'A practical guide to cloud accounting for small business owners who want less admin work and more clarity.',
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
    ];

    return json_encode([
        'id' => 'chatcmpl-test',
        'object' => 'chat.completion',
        'created' => 1,
        'model' => 'deepseek-v4-pro',
        'choices' => [[
            'index' => 0,
            'message' => ['role' => 'assistant', 'content' => json_encode($raw)],
            'finish_reason' => 'stop',
        ]],
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 20, 'total_tokens' => 30],
    ]);
}

it('drives provider calls through a PSR-18 client with the configured timeout', function () {
    $history = [];
    $stack = new HandlerStack(new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], fullGenerationPayload()),
    ]));
    $stack->push(Middleware::history($history));

    // Mirrors the AppServiceProvider binding: Guzzle (a PSR-18 client) is
    // injected with the config timeout so hung sockets cannot pin a worker.
    $http = new GuzzleHttp\Client([
        'handler' => $stack,
        'timeout' => (int) config('ai.timeout'),
    ]);

    $client = OpenAI::factory()
        ->withBaseUri('https://api.deepseek.com')
        ->withApiKey('test-key')
        ->withHttpClient($http)
        ->make();

    $provider = new DeepSeekProvider(
        app(GenerationValidator::class),
        app(SectionValidator::class),
        app(TitleValidator::class),
        $client
    );

    $result = $provider->generateVariation(new GenerationRequest(
        topic: 'T',
        primaryKeyword: 'kw',
        secondaryKeywords: [],
        tone: 'professional',
        targetPersona: null,
        targetWordCount: 500,
        additionalInstructions: null,
        angle: AngleType::Educational,
        negativeContext: [],
        promptContent: '{{topic}}',
        model: 'deepseek-v4-pro',
        maxTokens: 1000,
    ));

    expect($result->title)->toBe('Cloud Accounting for Small Businesses');
    expect($history)->toHaveCount(1);
    expect((string) $history[0]['request']->getUri())->toEndWith('/chat/completions');
    expect($history[0]['options']['timeout'] ?? null)->toBe((int) config('ai.timeout'));
});
