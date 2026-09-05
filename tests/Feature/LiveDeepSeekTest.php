<?php

declare(strict_types=1);

use App\AI\DeepSeek\DeepSeekProvider;
use App\AI\DTO\GenerationRequest;
use App\Enums\AngleType;
use Illuminate\Support\Facades\Validator;

it('gets valid structured JSON from the live DeepSeek API', function () {
    // Laravel's env() helper casts the string 'true' to a boolean,
    // so compare against true rather than the string 'true'.
    if (env('DEEPSEEK_RUN_LIVE_TESTS') !== true) {
        $this->markTestSkipped('Live tests disabled. Set DEEPSEEK_RUN_LIVE_TESTS=true to run.');
    }

    $provider = app(DeepSeekProvider::class);

    $request = new GenerationRequest(
        topic: 'Cloud accounting for small businesses',
        primaryKeyword: 'cloud accounting',
        secondaryKeywords: ['online accounting'],
        tone: 'professional',
        targetPersona: 'Small business owners',
        targetWordCount: 400,
        additionalInstructions: 'Keep it short and practical.',
        angle: AngleType::Educational,
        negativeContext: [],
        promptContent: <<<'PROMPT'
You are a blog writer. Write a short article.

TOPIC: {{topic}}
PRIMARY KEYWORD: {{primary_keyword}}
TONE: {{tone}}
ANGLE: {{angle}}

Return ONLY valid JSON matching this exact shape. No Markdown, no code fences:
{"title": "", "slug": "", "excerpt": "", "meta_title": "", "meta_description": "", "focus_keyword": "", "secondary_keywords": [], "category": "", "tags": [], "og_title": "", "og_description": "", "faq": [{"question": "", "answer": ""}], "schema_type": "Article", "sections": [{"heading": "", "body": ""}]}
PROMPT,
        model: config('ai.models.generation'),
        maxTokens: 4096,
    );

    $result = $provider->generateVariation($request);

    expect($result->title)->not->toBeEmpty();
    expect($result->sections)->not->toBeEmpty();
    expect($result->focusKeyword)->toBeString();
})->group('live');
