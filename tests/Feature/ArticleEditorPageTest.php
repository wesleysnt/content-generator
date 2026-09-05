<?php

declare(strict_types=1);

use App\Filament\Pages\ArticleEditor;
use App\Models\ContentRequest;
use App\Models\ContentVariation;
use App\Models\User;
use App\Services\RevisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function editorSetup(): array
{
    (new \Database\Seeders\PromptTemplateSeeder())->run();
    $writer = User::factory()->create(['role' => 'writer']);

    $request = ContentRequest::create([
        'user_id' => $writer->id,
        'topic' => 'T',
        'primary_keyword' => 'kw',
    ]);

    $variation = ContentVariation::create([
        'content_request_id' => $request->id,
        'variation_number' => 1,
        'angle_type' => 'educational',
        'status' => 'generated',
        'title' => 'Original',
        'slug' => 'original',
        'meta_title' => 'Original Meta',
        'meta_description' => 'Original meta description.',
        'focus_keyword' => 'kw',
    ]);

    $variation->sections()->create([
        'section_order' => 1,
        'heading' => 'Intro',
        'body' => '<p>Intro body.</p>',
    ]);

    app(RevisionService::class)->snapshot($variation, \App\Enums\RevisionType::AiGeneration, $writer->id);

    return [$writer, $variation];
}

it('queues a section regeneration job', function () {
    [$writer, $variation] = editorSetup();
    Queue::fake();

    Livewire::actingAs($writer)
        ->test(ArticleEditor::class, ['record' => $variation->id])
        ->call('regenerateSection', $variation->sections()->first()->id);

    Queue::assertPushed(\App\Jobs\RegenerateSectionJob::class, 1);
});

it('credits queued regeneration jobs to the acting user', function () {
    [$writer, $variation] = editorSetup();
    Queue::fake();

    Livewire::actingAs($writer)
        ->test(ArticleEditor::class, ['record' => $variation->id])
        ->call('regenerateSection', $variation->sections()->first()->id)
        ->call('regenerateTitle');

    Queue::assertPushed(
        \App\Jobs\RegenerateSectionJob::class,
        fn ($job) => $job->variationId === $variation->id && $job->userId === $writer->id
    );
    Queue::assertPushed(
        \App\Jobs\RegenerateTitleJob::class,
        fn ($job) => $job->variationId === $variation->id && $job->userId === $writer->id
    );
});

it('keeps the mounted variation client-tamper-proof', function () {
    [$writer, $variation] = editorSetup();
    $other = ContentVariation::create([
        'content_request_id' => $variation->request->id,
        'variation_number' => 2,
        'angle_type' => 'educational',
        'status' => 'generated',
        'title' => 'Other',
    ]);

    $component = Livewire::actingAs($writer)
        ->test(ArticleEditor::class, ['record' => $variation->id]);

    // Re-targeting the locked record at another variation is rejected.
    expect(fn () => $component->set('record', $other->id))
        ->toThrow(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);

    // Actions still hit the originally mounted variation only.
    Livewire::actingAs($writer)
        ->test(ArticleEditor::class, ['record' => $variation->id])
        ->call('markFinal')
        ->assertSet('record.id', $variation->id);

    expect($variation->fresh()->status)->toBe(\App\Enums\VariationStatus::Final);
    expect($other->fresh()->status)->toBe(\App\Enums\VariationStatus::Generated);
});

it('restores a revision and creates a new one', function () {
    [$writer, $variation] = editorSetup();

    $variation->update(['title' => 'Changed']);
    $revision = $variation->revisions()->first();

    Livewire::actingAs($writer)
        ->test(ArticleEditor::class, ['record' => $variation->id])
        ->call('restoreRevision', $revision->id);

    expect($variation->fresh()->title)->toBe('Original');
    expect($variation->revisions()->count())->toBe(2);
});

it('hides AI regeneration actions once the variation is marked final', function () {
    [$writer, $variation] = editorSetup();
    app(\App\Services\GenerationService::class)->markFinal($variation->id);

    Livewire::actingAs($writer)
        ->test(ArticleEditor::class, ['record' => $variation->id])
        ->assertSee('FINAL')
        ->assertDontSee('Regenerate Title')
        ->assertDontSee('Regenerate Section')
        ->assertDontSee('Mark Final');
});

it('saving without changes does not create a writer-edit revision', function () {
    [$writer, $variation] = editorSetup();

    Livewire::actingAs($writer)
        ->test(ArticleEditor::class, ['record' => $variation->id])
        ->call('save');

    // Unchanged saves used to bloat the history with no-op WriterEdit
    // snapshots; only an actual change may snapshot.
    expect($variation->fresh()->revisions()->count())->toBe(1);
    expect($variation->fresh()->title)->toBe('Original');
    expect($variation->fresh()->sections()->first()->body)->toBe('<p>Intro body.</p>');
});

it('a real edit still snapshots and persists', function () {
    [$writer, $variation] = editorSetup();

    $component = Livewire::actingAs($writer)
        ->test(ArticleEditor::class, ['record' => $variation->id]);

    $component
        ->set('formState.title', 'Renamed')
        ->set('sectionState.'.$variation->sections()->first()->id.'.body', '<p>Edited body.</p>')
        ->call('save');

    expect($variation->fresh()->title)->toBe('Renamed');
    expect($variation->fresh()->sections()->first()->body)->toBe('<p>Edited body.</p>');
    expect($variation->fresh()->revisions()->count())->toBe(2);
});
