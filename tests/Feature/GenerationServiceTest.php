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

it('allocates a generation token budget large enough for reasoning-model overhead', function () {
    [$writer] = setupWriter();

    $captured = new \App\AI\Fake\FakeAIProvider([$this->makeVariationResult()]);
    $probe = new class ($captured) implements AIProvider {
        public function __construct(private AIProvider $inner) {}

        public int $maxTokens = 0;

        public function generateVariation(\App\AI\DTO\GenerationRequest $request): \App\AI\DTO\VariationResult
        {
            $this->maxTokens = $request->maxTokens;

            return $this->inner->generateVariation($request);
        }

        public function regenerateVariation(\App\AI\DTO\GenerationRequest $request): \App\AI\DTO\VariationResult
        {
            return $this->inner->regenerateVariation($request);
        }

        public function regenerateSection(\App\AI\DTO\SectionRegenerationRequest $request): \App\AI\DTO\SectionResult
        {
            return $this->inner->regenerateSection($request);
        }

        public function regenerateTitle(\App\AI\DTO\TitleRegenerationRequest $request): \App\AI\DTO\TitleResult
        {
            return $this->inner->regenerateTitle($request);
        }
    };
    $this->app->bind(AIProvider::class, fn () => $probe);

    $service = app(GenerationService::class);
    $request = $service->createRequest($writer, [
        'topic' => 'Cloud accounting for SMBs',
        'primary_keyword' => 'cloud accounting',
        'variation_count' => 1,
        'target_word_count' => 1500,
    ]);
    $service->dispatchBatch($request);

    $service->generateVariation($request->variations()->first()->id);

    // Regression guard: with a plain 6_000-token cap the live provider
    // stopped at finish_reason=length before emitting the full 1,500-word
    // JSON article (reasoning tokens count against max_tokens).
    expect($probe->maxTokens)
        ->toBeGreaterThanOrEqual(12_000)
        ->toBeLessThanOrEqual(32_768);
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

it('keeps a completed request completed when a section regeneration fails', function () {
    [$writer] = setupWriter();
    $this->app->bind(AIProvider::class, fn () => new FakeAIProvider([
        $this->makeVariationResult(),
        new AICallException('Section provider error'),
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

    $service->regenerateSection($variation->id, $variation->sections()->first()->id);

    expect($variation->fresh()->error_message)->toContain('Section regeneration failed');
    expect($request->fresh()->status)->toBe(RequestStatus::Completed);
});

it('marks the variation failed when the repair attempt also fails validation', function () {
    [$writer] = setupWriter();
    $this->app->bind(AIProvider::class, fn () => new FakeAIProvider([
        new ValidationFailedException(['title' => ['The title field is required.']]),
        new ValidationFailedException(['title' => ['The title field is required.']]),
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

    $variation = $request->variations()->first()->fresh();
    expect($variation->status)->toBe(VariationStatus::Pending);
    expect($variation->error_message)->toContain('after repair');
    expect($variation->request->fresh()->status)->toBe(RequestStatus::Failed);
    expect(AiUsageLog::where('status', 'failed')->count())->toBe(1);
});

it('recomputes the request status after a regeneration attempt', function () {
    [$writer] = setupWriter();
    $this->app->bind(AIProvider::class, fn () => new FakeAIProvider([
        new AICallException('Provider API error: timeout'), // initial generation fails
        $this->makeVariationResult(),                        // regeneration succeeds
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
    expect($variation->fresh()->request->fresh()->status)->toBe(RequestStatus::Failed);

    $service->regenerateVariation($variation->id);

    // Regression guard: regenerateVariation used to never call
    // updateRequestStatus, leaving the request header stuck on "failed"
    // (or "queued"/"processing") even after a later attempt succeeded.
    expect($variation->fresh()->status)->toBe(VariationStatus::Generated);
    expect($variation->fresh()->request->fresh()->status)->toBe(RequestStatus::Completed);
});

it('is a no-op when a batch job re-runs for a variation whose content is already decided', function () {
    [$writer] = setupWriter();
    $this->app->bind(AIProvider::class, fn () => new FakeAIProvider([
        $this->makeVariationResult(),
        $this->makeVariationResult(),
        $this->makeVariationResult(),
        $this->makeVariationResult(['title' => 'Would-Overwrite Title']),
    ]));

    $service = app(GenerationService::class);
    $request = $service->createRequest($writer, [
        'topic' => 'Cloud accounting',
        'primary_keyword' => 'cloud accounting',
        'variation_count' => 3,
        'target_word_count' => 100,
    ]);
    $service->dispatchBatch($request);
    $variations = $request->variations()->get();

    $service->generateVariation($variations[0]->id);
    $service->generateVariation($variations[1]->id);
    $service->generateVariation($variations[2]->id);
    expect(AiUsageLog::count())->toBe(3);

    // Variation 1 is locked, 2 generated, 3 discarded — none of them may be
    // overwritten by a duplicate/stale batch job (e.g. re-dispatched after a
    // worker restart), which is exactly what happened in the smoke run:
    // locked v5 accumulated three sequential full generations.
    $service->lock($variations[0]->id);
    $service->discard($variations[2]->id);

    $service->generateVariation($variations[0]->id);
    $service->generateVariation($variations[1]->id);
    $service->generateVariation($variations[2]->id);

    expect($variations[0]->fresh()->title)->toBe('Cloud Accounting for Small Businesses');
    expect($variations[0]->fresh()->is_locked)->toBeTrue();
    expect($variations[1]->fresh()->title)->toBe('Cloud Accounting for Small Businesses');
    expect($variations[2]->fresh()->status)->toBe(VariationStatus::Discarded);
    expect(AiUsageLog::count())->toBe(3);
    expect($request->fresh()->status)->toBe(RequestStatus::Completed);
});

it('passes locked variation titles as negative context during regeneration', function () {
    [$writer] = setupWriter();

    $inner = new FakeAIProvider([
        $this->makeVariationResult(['title' => 'First Angle Article']),
        $this->makeVariationResult(['title' => 'Second Angle Article']),
        $this->makeVariationResult(['title' => 'Second Angle Rewritten']),
    ]);
    $captured = [];
    $probe = new class ($inner) implements AIProvider {
        public function __construct(private AIProvider $inner) {}

        /** @var array<int, array{negativeContext: array, variationNumber: int}> */
        public array $seen = [];

        public function generateVariation(\App\AI\DTO\GenerationRequest $request): \App\AI\DTO\VariationResult
        {
            return $this->inner->generateVariation($request);
        }

        public function regenerateVariation(\App\AI\DTO\GenerationRequest $request): \App\AI\DTO\VariationResult
        {
            $this->seen[] = $request->negativeContext;

            return $this->inner->regenerateVariation($request);
        }

        public function regenerateSection(\App\AI\DTO\SectionRegenerationRequest $request): \App\AI\DTO\SectionResult
        {
            return $this->inner->regenerateSection($request);
        }

        public function regenerateTitle(\App\AI\DTO\TitleRegenerationRequest $request): \App\AI\DTO\TitleResult
        {
            return $this->inner->regenerateTitle($request);
        }
    };
    $this->app->bind(AIProvider::class, fn () => $probe);

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

    // Variation 1 is locked after generation; regenerating variation 2 must
    // see variation 1's title (and excerpt) as negative context.
    $service->lock($variations[0]->id);
    $service->regenerateVariation($variations[1]->id);

    expect($probe->seen)->toHaveCount(1);
    $context = implode(' ', $probe->seen[0]);
    expect($context)->toContain('First Angle Article');
    expect($context)->not->toContain('Second Angle');
    expect($variations[1]->fresh()->title)->toBe('Second Angle Rewritten');
});

it('refuses every regeneration entry point once a variation is final', function () {
    [$writer] = setupWriter();
    $this->app->bind(AIProvider::class, fn () => new FakeAIProvider([
        $this->makeVariationResult(),
        $this->makeVariationResult(['title' => 'Would-Overwrite Title']),
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
    $service->markFinal($variation->id);
    $sectionId = $variation->sections()->first()->id;

    // A Final variation is terminal: no AI write-back from a queued or
    // manual regeneration job may touch it (the queued RegenerateContentJob,
    // RegenerateSectionJob and RegenerateTitleJob all land here).
    expect(fn () => $service->regenerateVariation($variation->id))
        ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
    expect(fn () => $service->regenerateSection($variation->id, $sectionId))
        ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
    expect(fn () => $service->regenerateTitle($variation->id))
        ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);

    // No side effects from the aborted attempts: content, revisions and
    // usage logs are untouched.
    $variation->refresh();
    expect($variation->title)->toBe('Cloud Accounting for Small Businesses');
    expect($variation->status)->toBe(VariationStatus::Final);
    expect($variation->revisions()->count())->toBe(1);
    expect(AiUsageLog::count())->toBe(1);
});

it('converges the request status when a duplicate batch job no-ops', function () {
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
    expect($request->fresh()->status)->toBe(RequestStatus::Completed);

    // Simulate a worker killed between the success transaction commit and
    // updateRequestStatus: the header is stuck on "processing". A duplicate
    // GenerateContentJob then no-ops (variation already generated) and must
    // still converge the request header back to Completed.
    $request->update(['status' => RequestStatus::Processing]);

    $service->generateVariation($variations[0]->id);

    expect($request->fresh()->status)->toBe(RequestStatus::Completed);
});
