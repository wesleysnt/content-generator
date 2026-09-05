<?php

declare(strict_types=1);

use App\Enums\VariationStatus;
use App\Filament\Pages\GenerationWorkspace;
use App\Models\ContentRequest;
use App\Models\ContentVariation;
use App\Models\User;
use App\Services\GenerationService;
use App\Services\SettingsService;
use App\Services\UsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Helpers\MakesVariationResult;

uses(RefreshDatabase::class, MakesVariationResult::class);

function logMonthlyGenerations(User $user, int $count): void
{
    $request = ContentRequest::create([
        'user_id' => $user->id,
        'topic' => 'L',
        'primary_keyword' => 'lkw',
    ]);

    $service = app(UsageService::class);

    for ($i = 0; $i < $count; $i++) {
        $service->record($user, $request, null, 'deepseek-v4-pro', 'generation', 10, 10, 5);
    }
}

it('rejects a new request once the monthly generation limit is reached', function () {
    $writer = User::factory()->create(['role' => 'writer']);
    app(SettingsService::class)->set('max_monthly_generations', 2);
    logMonthlyGenerations($writer, 2);

    $service = app(GenerationService::class);

    expect(fn () => $service->createRequest($writer, [
        'topic' => 'Cloud accounting',
        'primary_keyword' => 'cloud accounting',
        'variation_count' => 1,
        'target_word_count' => 100,
    ]))->toThrow(HttpException::class, 'Monthly generation limit reached.');

    expect(ContentRequest::count())->toBe(1); // only the log fixture request
});

it('accounts for the requested variation count against the limit', function () {
    $writer = User::factory()->create(['role' => 'writer']);
    app(SettingsService::class)->set('max_monthly_generations', 3);
    logMonthlyGenerations($writer, 2);

    $service = app(GenerationService::class);

    // One remaining slot: a single variation fits, a batch of three does not.
    $single = $service->createRequest($writer, [
        'topic' => 'T1',
        'primary_keyword' => 'kw',
        'variation_count' => 1,
        'target_word_count' => 100,
    ]);
    expect($single->variation_count)->toBe(1);

    expect(fn () => $service->createRequest($writer, [
        'topic' => 'T2',
        'primary_keyword' => 'kw',
        'variation_count' => 3,
        'target_word_count' => 100,
    ]))->toThrow(HttpException::class, 'Monthly generation limit reached.');
});

it('allows a new request when the limit is not yet reached', function () {
    $writer = User::factory()->create(['role' => 'writer']);
    app(SettingsService::class)->set('max_monthly_generations', 5);
    logMonthlyGenerations($writer, 2);
    Queue::fake();

    $service = app(GenerationService::class);
    $request = $service->createRequest($writer, [
        'topic' => 'Cloud accounting',
        'primary_keyword' => 'cloud accounting',
        'variation_count' => 2,
        'target_word_count' => 100,
    ]);
    $service->dispatchBatch($request);

    expect(ContentRequest::where('topic', 'Cloud accounting')->count())->toBe(1);
    Queue::assertPushed(\App\Jobs\GenerateContentJob::class, 2);
});

it('refuses regenerations once the limit is reached', function () {
    $writer = User::factory()->create(['role' => 'writer']);
    app(SettingsService::class)->set('max_monthly_generations', 2);
    logMonthlyGenerations($writer, 2);

    $service = app(GenerationService::class);
    $request = ContentRequest::create([
        'user_id' => $writer->id,
        'topic' => 'R',
        'primary_keyword' => 'rkw',
        'status' => 'completed',
    ]);
    $variation = ContentVariation::create([
        'content_request_id' => $request->id,
        'variation_number' => 1,
        'angle_type' => 'educational',
        'status' => 'generated',
        'title' => 'R',
    ]);
    $sectionId = $variation->sections()->create([
        'section_order' => 1,
        'heading' => 'H',
        'body' => '<p>B</p>',
    ])->id;

    expect(fn () => $service->regenerateVariation($variation->id, null, $writer))
        ->toThrow(HttpException::class, 'Monthly generation limit reached.');
    expect(fn () => $service->regenerateSection($variation->id, $sectionId, null, $writer))
        ->toThrow(HttpException::class, 'Monthly generation limit reached.');
    expect(fn () => $service->regenerateTitle($variation->id, null, $writer))
        ->toThrow(HttpException::class, 'Monthly generation limit reached.');
    expect(fn () => $service->regenerateUnlocked($request, $writer))
        ->toThrow(HttpException::class, 'Monthly generation limit reached.');

    expect($variation->fresh()->status)->toBe(VariationStatus::Generated);
    expect($variation->revisions()->count())->toBe(0);
});

it('does not cap queued regeneration jobs already in flight', function () {
    (new \Database\Seeders\PromptTemplateSeeder())->run();
    $writer = User::factory()->create(['role' => 'writer']);
    app(SettingsService::class)->set('max_monthly_generations', 2);
    logMonthlyGenerations($writer, 2);

    $this->app->bind(\App\AI\Contracts\AIProvider::class, fn () => new \App\AI\Fake\FakeAIProvider([
        $this->makeVariationResult(['title' => 'Regenerated', 'slug' => 'regenerated']),
    ]));

    $service = app(GenerationService::class);
    $request = ContentRequest::create([
        'user_id' => $writer->id,
        'topic' => 'R',
        'primary_keyword' => 'rkw',
        'status' => 'completed',
    ]);
    $variation = ContentVariation::create([
        'content_request_id' => $request->id,
        'variation_number' => 1,
        'angle_type' => 'educational',
        'status' => 'generated',
        'title' => 'R',
    ]);

    // The queue worker path carries no acting user, so a job dispatched
    // before the cap was crossed may still run.
    (new \App\Jobs\RegenerateContentJob($variation->id, $writer->id))->handle($service);

    expect($variation->fresh()->title)->toBe('Regenerated');
});

it('does not queue a workspace regeneration once the limit is reached', function () {
    $writer = User::factory()->create(['role' => 'writer']);
    app(SettingsService::class)->set('max_monthly_generations', 2);
    logMonthlyGenerations($writer, 2);

    $request = ContentRequest::create([
        'user_id' => $writer->id,
        'topic' => 'R',
        'primary_keyword' => 'rkw',
        'status' => 'completed',
    ]);
    $variation = ContentVariation::create([
        'content_request_id' => $request->id,
        'variation_number' => 1,
        'angle_type' => 'educational',
        'status' => 'generated',
        'title' => 'R',
    ]);
    Queue::fake();

    Livewire::actingAs($writer)
        ->test(GenerationWorkspace::class, ['record' => $request->id])
        ->call('regenerateVariation', $variation->id)
        ->assertStatus(403);

    Queue::assertNothingPushed();
});
