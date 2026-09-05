<?php

declare(strict_types=1);

use App\AI\Contracts\AIProvider;
use App\AI\Exceptions\AICallException;
use App\AI\Exceptions\ValidationFailedException;
use App\AI\Fake\FakeAIProvider;
use App\Enums\RequestStatus;
use App\Enums\RevisionType;
use App\Enums\VariationStatus;
use App\Jobs\GenerateContentJob;
use App\Jobs\RegenerateContentJob;
use App\Models\AiUsageLog;
use App\Models\User;
use App\Services\GenerationService;
use Database\Seeders\PromptTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Helpers\MakesVariationResult;

uses(RefreshDatabase::class, MakesVariationResult::class);

function setupWriter(): array
{
    $writer = User::factory()->create(['role' => 'writer']);
    (new PromptTemplateSeeder)->run();

    return [$writer];
}

it('creates a request and dispatches one job per variation', function () {
    [$writer] = setupWriter();
    Queue::fake();

    $service = app(GenerationService::class);
    $request = $service->createRequest($writer, [
        'topic' => 'Cloud accounting for SMBs',
        'primary_keyword' => 'cloud accounting',
        'secondary_keywords' => ['online accounting'],
        'tone' => 'professional',
        'target_persona' => 'Small business owners',
        'variation_count' => 3,
        'target_word_count' => 1500,
        'additional_instructions' => 'Be practical.',
    ]);

    $service->dispatchBatch($request);

    expect($request->status)->toBe(RequestStatus::Queued);
    expect($request->variations()->count())->toBe(3);
    expect($request->variations()->pluck('angle_type')->unique()->count())->toBe(3);
    Queue::assertPushed(GenerateContentJob::class, 3);
});

it('generates a variation end to end with the fake provider', function () {
    [$writer] = setupWriter();
    $this->app->bind(AIProvider::class, fn () => new FakeAIProvider([$this->makeVariationResult()]));

    $service = app(GenerationService::class);
    $request = $service->createRequest($writer, [
        'topic' => 'Cloud accounting for SMBs',
        'primary_keyword' => 'cloud accounting',
        'variation_count' => 1,
        'target_word_count' => 100,
    ]);
    $service->dispatchBatch($request);

    $variation = $request->variations()->first();
    $service->generateVariation($variation->id);

    $variation->refresh();
    expect($variation->status)->toBe(VariationStatus::Generated);
    expect($variation->title)->toBe('Cloud Accounting for Small Businesses');
    expect($variation->sections()->count())->toBe(2);
    expect($variation->revisions()->count())->toBe(1);
    expect($variation->revisions()->first()->revision_type)->toBe(RevisionType::AiGeneration);
    expect($variation->prompt_tokens)->toBe(100);
    expect($variation->request->fresh()->status)->toBe(RequestStatus::Completed);
    expect(AiUsageLog::count())->toBe(1);
});

it('repairs once on validation failure then succeeds', function () {
    [$writer] = setupWriter();
    $this->app->bind(AIProvider::class, fn () => new FakeAIProvider([
        new ValidationFailedException(['title' => ['The title field is required.']]),
        $this->makeVariationResult(),
    ]));

    $service = app(GenerationService::class);
    $request = $service->createRequest($writer, [
        'topic' => 'Cloud accounting',
        'primary_keyword' => 'cloud accounting',
        'variation_count' => 1,
        'target_word_count' => 100,
    ]);
    $service->dispatchBatch($request);

    $variation = $request->variations()->first();
    $service->generateVariation($variation->id);

    $variation->refresh();
    expect($variation->status)->toBe(VariationStatus::Generated);
    expect($variation->revisions()->count())->toBe(1);
});

it('marks variation failed on provider error', function () {
    [$writer] = setupWriter();
    $this->app->bind(AIProvider::class, fn () => new FakeAIProvider([
        new AICallException('Provider API error: timeout'),
    ]));

    $service = app(GenerationService::class);
    $request = $service->createRequest($writer, [
        'topic' => 'Cloud accounting',
        'primary_keyword' => 'cloud accounting',
        'variation_count' => 1,
        'target_word_count' => 100,
    ]);
    $service->dispatchBatch($request);

    $variation = $request->variations()->first();
    $service->generateVariation($variation->id);

    $variation->refresh();
    expect($variation->status)->toBe(VariationStatus::Pending);
    expect($variation->error_message)->toBe('Provider API error: timeout');
    expect($variation->request->fresh()->status)->toBe(RequestStatus::Failed);
});

it('lock protects variation from regenerateUnlocked', function () {
    [$writer] = setupWriter();
    $this->app->bind(AIProvider::class, fn () => new FakeAIProvider([
        $this->makeVariationResult(),
        $this->makeVariationResult(),
    ]));

    $service = app(GenerationService::class);
    $request = $service->createRequest($writer, [
        'topic' => 'Cloud accounting',
        'primary_keyword' => 'cloud accounting',
        'variation_count' => 2,
        'target_word_count' => 100,
    ]);
    $service->dispatchBatch($request);

    $variations = $request->variations()->get();
    $service->generateVariation($variations[0]->id);
    $service->generateVariation($variations[1]->id);

    $service->lock($variations[0]->id);
    Queue::fake();
    $service->regenerateUnlocked($request);

    Queue::assertPushed(RegenerateContentJob::class, 1);
    expect($variations[0]->fresh()->is_locked)->toBeTrue();
});

it('sanitizes section HTML on persist', function () {
    [$writer] = setupWriter();
    $this->app->bind(AIProvider::class, fn () => new FakeAIProvider([
        $this->makeVariationResult([
            'sections' => [
                ['heading' => 'H', 'body' => '<p>Safe</p><script>alert(1)</script><img src="x" onerror="alert(1)">'],
            ],
        ]),
    ]));

    $service = app(GenerationService::class);
    $request = $service->createRequest($writer, [
        'topic' => 'Cloud accounting',
        'primary_keyword' => 'cloud accounting',
        'variation_count' => 1,
        'target_word_count' => 100,
    ]);
    $service->dispatchBatch($request);
    $service->generateVariation($request->variations()->first()->id);

    $body = $request->variations()->first()->sections()->first()->body;
    expect($body)->toContain('<p>Safe</p>');
    expect($body)->not->toContain('<script>');
    expect($body)->not->toContain('onerror');
});
