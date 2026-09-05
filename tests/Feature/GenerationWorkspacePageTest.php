<?php

declare(strict_types=1);

use App\Filament\Pages\GenerationWorkspace;
use App\Models\ContentRequest;
use App\Models\ContentVariation;
use App\Models\User;
use App\Services\GenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function workspaceSetup(): array
{
    (new \Database\Seeders\PromptTemplateSeeder())->run();
    $writer = User::factory()->create(['role' => 'writer']);

    $request = ContentRequest::create([
        'user_id' => $writer->id,
        'topic' => 'T',
        'primary_keyword' => 'kw',
        'status' => 'completed',
    ]);

    foreach ([1, 2] as $i) {
        ContentVariation::create([
            'content_request_id' => $request->id,
            'variation_number' => $i,
            'angle_type' => 'educational',
            'status' => 'generated',
            'title' => "Title {$i}",
        ]);
    }

    return [$writer, $request];
}

it('locks a variation', function () {
    [$writer, $request] = workspaceSetup();
    $variation = $request->variations()->first();

    Livewire::actingAs($writer)
        ->test(GenerationWorkspace::class, ['record' => $request->id])
        ->call('lock', $variation->id);

    expect($variation->fresh()->is_locked)->toBeTrue();
});

it('regenerates only unlocked variations', function () {
    [$writer, $request] = workspaceSetup();
    $variations = $request->variations()->get();
    app(GenerationService::class)->lock($variations[0]->id);

    Queue::fake();

    Livewire::actingAs($writer)
        ->test(GenerationWorkspace::class, ['record' => $request->id])
        ->call('regenerateUnlocked');

    Queue::assertPushed(\App\Jobs\RegenerateContentJob::class, 1);
});

it('denies access to another writers request', function () {
    [$writer, $request] = workspaceSetup();
    $other = User::factory()->create(['role' => 'writer']);

    Livewire::actingAs($other)
        ->test(GenerationWorkspace::class, ['record' => $request->id])
        ->assertForbidden();
});

it('excludes finalized variations from regenerateUnlocked', function () {
    [$writer, $request] = workspaceSetup();
    $variations = $request->variations()->get();
    app(GenerationService::class)->markFinal($variations[0]->id);
    app(GenerationService::class)->markFinal($variations[1]->id);

    Queue::fake();

    Livewire::actingAs($writer)
        ->test(GenerationWorkspace::class, ['record' => $request->id])
        ->call('regenerateUnlocked');

    // A variation the writer chose as final must not be re-queued by the
    // batch "Regenerate Unlocked" action.
    Queue::assertNothingPushed();
});

it('renders finalized variations as openable with a FINAL badge', function () {
    [$writer, $request] = workspaceSetup();
    [$v1, $v2] = $request->variations()->get();
    app(GenerationService::class)->markFinal($v1->id);

    Livewire::actingAs($writer)
        ->test(GenerationWorkspace::class, ['record' => $request->id])
        ->assertSee('FINAL')
        ->assertSee('/admin/articles/' . $v1->id . '/edit')
        ->assertSee('/admin/articles/' . $v2->id . '/edit');
});

it('only touches variations that belong to the mounted request', function () {
    [$writer, $request] = workspaceSetup();
    $variation = $request->variations()->first();

    // Same owner, different request: the client-supplied variation id alone
    // must not make a variation addressable from this workspace.
    $foreignRequest = ContentRequest::create([
        'user_id' => $writer->id,
        'topic' => 'F',
        'primary_keyword' => 'fkw',
        'status' => 'completed',
    ]);
    $foreignVariation = ContentVariation::create([
        'content_request_id' => $foreignRequest->id,
        'variation_number' => 1,
        'angle_type' => 'educational',
        'status' => 'generated',
        'title' => 'Theirs',
    ]);

    $component = Livewire::actingAs($writer)
        ->test(GenerationWorkspace::class, ['record' => $request->id]);

    expect(fn () => $component->call('lock', $foreignVariation->id))
        ->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);

    expect($foreignVariation->fresh()->is_locked)->toBeFalse();
    expect($variation->fresh()->is_locked)->toBeFalse();
});

it('shows a failed variation with a retry that restarts the batch job', function () {
    [$writer, $request] = workspaceSetup();
    $variation = $request->variations()->first();
    $variation->update([
        'status' => 'pending',
        'error_message' => 'Provider API error: timeout',
    ]);
    Queue::fake();

    $component = Livewire::actingAs($writer)
        ->test(GenerationWorkspace::class, ['record' => $request->id])
        ->assertSee('FAILED')
        ->assertSee('Provider API error: timeout')
        ->assertSee('Retry');

    $component->call('retryVariation', $variation->id);

    Queue::assertPushed(\App\Jobs\GenerateContentJob::class, 1);
});

it('shows a retry on generated content whose regeneration failed', function () {
    [$writer, $request] = workspaceSetup();
    $variation = $request->variations()->first();
    $variation->update([
        'error_message' => 'Section regeneration failed: Provider API error: timeout',
    ]);
    Queue::fake();

    $component = Livewire::actingAs($writer)
        ->test(GenerationWorkspace::class, ['record' => $request->id])
        ->assertSee('Section regeneration failed')
        ->assertSee('Retry')
        ->assertSee('Title 1');

    $component->call('retryVariation', $variation->id);

    Queue::assertPushed(\App\Jobs\RegenerateContentJob::class, 1);
    Queue::assertPushed(\App\Jobs\RegenerateContentJob::class, fn ($job) => $job->userId === $writer->id);
});

it('does not offer a retry for locked variations', function () {
    [$writer, $request] = workspaceSetup();
    $variation = $request->variations()->first();
    $variation->update([
        'is_locked' => true,
        'error_message' => 'Section regeneration failed',
    ]);

    Livewire::actingAs($writer)
        ->test(GenerationWorkspace::class, ['record' => $request->id])
        ->assertSee('Section regeneration failed')
        ->assertDontSee('Retry');
});

it('keeps the mounted record client-tamper-proof', function () {
    [$writer, $request] = workspaceSetup();
    [$v1] = $request->variations()->get();
    $other = ContentRequest::create([
        'user_id' => $writer->id,
        'topic' => 'F',
        'primary_keyword' => 'fkw',
        'status' => 'completed',
    ]);

    $component = Livewire::actingAs($writer)
        ->test(GenerationWorkspace::class, ['record' => $request->id]);

    // Swapping the locked record would re-target every action at another
    // request; the client update is rejected outright.
    expect(fn () => $component->set('record', $other->id))
        ->toThrow(CannotUpdateLockedPropertyException::class);

    // Actions still operate on the originally mounted request.
    Livewire::actingAs($writer)
        ->test(GenerationWorkspace::class, ['record' => $request->id])
        ->call('lock', $v1->id)
        ->assertSet('record.id', $request->id);

    expect($v1->fresh()->is_locked)->toBeTrue();
});
