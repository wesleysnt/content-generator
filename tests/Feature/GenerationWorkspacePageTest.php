<?php

declare(strict_types=1);

use App\Filament\Pages\GenerationWorkspace;
use App\Models\ContentRequest;
use App\Models\ContentVariation;
use App\Models\User;
use App\Services\GenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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
