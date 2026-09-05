<?php

declare(strict_types=1);

use App\Enums\AngleType;
use App\Enums\RequestStatus;
use App\Enums\VariationStatus;
use App\Models\ContentRequest;
use App\Models\ContentVariation;
use App\Models\PromptTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a request with variations, sections and revisions', function () {
    $user = User::factory()->create();

    $request = ContentRequest::create([
        'user_id' => $user->id,
        'topic' => 'Cloud accounting for SMBs',
        'primary_keyword' => 'cloud accounting',
        'variation_count' => 3,
    ]);

    $variation = ContentVariation::create([
        'content_request_id' => $request->id,
        'variation_number' => 1,
        'angle_type' => AngleType::Educational->value,
    ]);

    $variation->sections()->create([
        'section_order' => 1,
        'heading' => 'Why Cloud Accounting',
        'body' => '<p>Body</p>',
    ]);

    $variation->revisions()->create([
        'snapshot' => ['title' => 'Old'],
        'revision_type' => 'ai_generation',
    ]);

    expect($user->contentRequests()->count())->toBe(1);
    expect($request->variations()->count())->toBe(1);
    expect($variation->sections()->count())->toBe(1);
    expect($variation->revisions()->count())->toBe(1);
    expect($variation->request->id)->toBe($request->id);
    expect($variation->status)->toBeInstanceOf(VariationStatus::class);
    expect($request->status)->toBe(RequestStatus::Draft);
});

it('finds the active prompt version', function () {
    $template = PromptTemplate::create(['key' => 'generation', 'name' => 'Generation']);

    $v1 = $template->versions()->create(['version' => 1, 'content' => 'v1', 'is_active' => false]);
    $v2 = $template->versions()->create(['version' => 2, 'content' => 'v2', 'is_active' => true]);

    expect($template->activeVersion()->first()->id)->toBe($v2->id);
});
