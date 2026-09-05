<?php

declare(strict_types=1);

use App\AI\Contracts\AIProvider;
use App\AI\Fake\FakeAIProvider;
use App\Enums\VariationStatus;
use App\Jobs\GenerateContentJob;
use App\Jobs\RegenerateSectionJob;
use App\Models\ContentRequest;
use App\Models\ContentVariation;
use App\Models\User;
use App\Services\GenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Helpers\MakesVariationResult;

uses(RefreshDatabase::class, MakesVariationResult::class);

it('runs GenerateContentJob through the service', function () {
    (new \Database\Seeders\PromptTemplateSeeder())->run();
    $writer = User::factory()->create(['role' => 'writer']);
    $this->app->bind(AIProvider::class, fn () => new FakeAIProvider([$this->makeVariationResult()]));

    $service = app(GenerationService::class);
    $request = $service->createRequest($writer, [
        'topic' => 'T', 'primary_keyword' => 'kw', 'variation_count' => 1, 'target_word_count' => 100,
    ]);
    $service->dispatchBatch($request);
    $variation = $request->variations()->first();

    (new GenerateContentJob($variation->id))->handle(app(GenerationService::class));

    expect($variation->fresh()->status)->toBe(VariationStatus::Generated);
});

it('is unique per variation id', function () {
    expect((new GenerateContentJob(42))->uniqueId())->toBe(42);
});

it('runs RegenerateSectionJob through the service', function () {
    (new \Database\Seeders\PromptTemplateSeeder())->run();
    $writer = User::factory()->create(['role' => 'writer']);

    $request = ContentRequest::create([
        'user_id' => $writer->id, 'topic' => 'T', 'primary_keyword' => 'kw',
    ]);
    $variation = ContentVariation::create([
        'content_request_id' => $request->id, 'variation_number' => 1,
        'angle_type' => 'educational', 'status' => 'generated', 'title' => 'T',
    ]);
    $section = $variation->sections()->create([
        'section_order' => 1, 'heading' => 'H', 'body' => '<p>B</p>',
    ]);

    $this->app->bind(AIProvider::class, fn () => new FakeAIProvider([
        new \App\AI\DTO\SectionResult('H', '<p>New body</p>', 10, 20, 'deepseek-v4-pro', ['heading' => 'H', 'body' => '<p>New body</p>']),
    ]));

    (new RegenerateSectionJob($variation->id, $section->id))->handle(app(GenerationService::class));

    expect($section->fresh()->body)->toBe('<p>New body</p>');
});
