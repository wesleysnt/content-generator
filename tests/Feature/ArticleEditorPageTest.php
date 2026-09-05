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
