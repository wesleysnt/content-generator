<?php

declare(strict_types=1);

use App\Models\PromptTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds four prompt templates with active versions', function () {
    $this->seed();

    foreach (['generation', 'regeneration', 'section_regeneration', 'title_regeneration'] as $key) {
        $template = PromptTemplate::where('key', $key)->firstOrFail();
        expect($template->versions()->count())->toBeGreaterThanOrEqual(1);
        expect($template->activeVersion()->first())->not->toBeNull();
    }
});

it('seeds an admin user', function () {
    $this->seed();

    $admin = User::where('email', 'admin@example.com')->firstOrFail();
    expect($admin->role)->toBe('admin');
});
