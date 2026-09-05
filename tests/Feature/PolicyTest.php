<?php

declare(strict_types=1);

use App\Models\ContentRequest;
use App\Models\ContentVariation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows the owner to view their request', function () {
    $writer = User::factory()->create(['role' => 'writer']);
    $request = ContentRequest::create(['user_id' => $writer->id, 'topic' => 'T', 'primary_keyword' => 'kw']);

    expect($writer->can('view', $request))->toBeTrue();
});

it('denies other writers from viewing the request', function () {
    $owner = User::factory()->create(['role' => 'writer']);
    $other = User::factory()->create(['role' => 'writer']);
    $request = ContentRequest::create(['user_id' => $owner->id, 'topic' => 'T', 'primary_keyword' => 'kw']);

    expect($other->can('view', $request))->toBeFalse();
});

it('allows admins to view any request', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $owner = User::factory()->create(['role' => 'writer']);
    $request = ContentRequest::create(['user_id' => $owner->id, 'topic' => 'T', 'primary_keyword' => 'kw']);

    expect($admin->can('view', $request))->toBeTrue();
});

it('only admins can manage prompts', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $writer = User::factory()->create(['role' => 'writer']);

    expect($admin->can('viewAny', \App\Models\PromptTemplate::class))->toBeTrue();
    expect($writer->can('viewAny', \App\Models\PromptTemplate::class))->toBeFalse();
});
