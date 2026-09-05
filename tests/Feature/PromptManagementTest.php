<?php

declare(strict_types=1);

use App\Models\PromptTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('only admins can view prompt templates resource', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $writer = User::factory()->create(['role' => 'writer']);

    expect($admin->can('viewAny', PromptTemplate::class))->toBeTrue();
    expect($writer->can('viewAny', PromptTemplate::class))->toBeFalse();
});

it('creating a version auto-numbers it as max + 1', function () {
    $template = PromptTemplate::create(['key' => 'generation', 'name' => 'Generation']);
    $template->versions()->create(['version' => 1, 'content' => 'v1', 'is_active' => true]);
    $template->versions()->create(['version' => 2, 'content' => 'v2', 'is_active' => false]);

    $next = $template->versions()->max('version') + 1;

    expect($next)->toBe(3);
});
