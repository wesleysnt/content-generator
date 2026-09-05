<?php

declare(strict_types=1);

// tests/Unit/RevisionServiceTest.php

use App\Enums\RevisionType;
use App\Models\ContentRequest;
use App\Models\ContentVariation;
use App\Models\User;
use App\Services\RevisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeVariation(): ContentVariation
{
    $user = User::factory()->create();
    $request = ContentRequest::create([
        'user_id' => $user->id,
        'topic' => 'T',
        'primary_keyword' => 'kw',
    ]);

    return ContentVariation::create([
        'content_request_id' => $request->id,
        'variation_number' => 1,
        'angle_type' => 'educational',
        'title' => 'Original Title',
        'slug' => 'original-title',
    ]);
}

it('snapshots the full variation state', function () {
    $variation = makeVariation();
    $variation->sections()->create(['section_order' => 1, 'heading' => 'H', 'body' => '<p>B</p>']);

    $revision = app(RevisionService::class)->snapshot($variation, RevisionType::AiGeneration, null);

    expect($revision->snapshot['title'])->toBe('Original Title');
    expect($revision->snapshot['sections'])->toHaveCount(1);
    expect($revision->revision_type)->toBe(RevisionType::AiGeneration);
});

it('restore writes snapshot back and creates a pre-restore revision', function () {
    $service = app(RevisionService::class);
    $variation = makeVariation();
    $service->snapshot($variation, RevisionType::AiGeneration, null);

    $variation->update(['title' => 'Changed Title']);
    $revision = $variation->revisions()->latest()->first();

    $service->restore($variation, $revision, null);

    $variation->refresh();
    expect($variation->title)->toBe('Original Title');
    expect($variation->revisions()->count())->toBe(2);
    expect($variation->revisions()->first()->revision_type)->toBe(RevisionType::Restore);
    expect($variation->revisions()->first()->snapshot['title'])->toBe('Changed Title');
});
