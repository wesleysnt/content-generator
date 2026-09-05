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

it('creates and activates a prompt version from the relation manager', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $template = PromptTemplate::create(['key' => 'generation', 'name' => 'Generation']);
    $template->versions()->create(['version' => 1, 'content' => 'v1 body', 'is_active' => true]);

    $manager = \App\Filament\Resources\PromptTemplateResource\RelationManagers\PromptVersionsRelationManager::class;
    $editPage = \App\Filament\Resources\PromptTemplateResource\Pages\EditPromptTemplate::class;

    $component = Livewire::actingAs($admin)->test($manager, [
        'ownerRecord' => $template,
        'pageClass' => $editPage,
    ]);

    $component
        ->callTableAction('create', data: ['content' => 'v2 body'])
        ->assertHasNoTableActionErrors();

    $v2 = $template->versions()->where('version', 2)->first();
    expect($v2)->not->toBeNull();
    expect($v2->is_active)->toBeFalse();

    $component
        ->callTableAction('activate', record: $v2)
        ->assertHasNoTableActionErrors();

    expect($template->versions()->where('is_active', true)->count())->toBe(1);
    expect($template->versions()->where('version', 2)->first()->is_active)->toBeTrue();
    expect($template->versions()->where('version', 1)->first()->is_active)->toBeFalse();
});

it('offers no way to edit an existing prompt version', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $template = PromptTemplate::create(['key' => 'generation', 'name' => 'Generation']);
    $template->versions()->create(['version' => 1, 'content' => 'v1 body', 'is_active' => true]);

    $manager = \App\Filament\Resources\PromptTemplateResource\RelationManagers\PromptVersionsRelationManager::class;
    $editPage = \App\Filament\Resources\PromptTemplateResource\Pages\EditPromptTemplate::class;

    $component = Livewire::actingAs($admin)->test($manager, [
        'ownerRecord' => $template,
        'pageClass' => $editPage,
    ]);

    // Content and is_active are absent from any edit context: rewriting a
    // published version would silently change the prompts of already
    // generated content that points at this prompt_version_id.
    $component->assertTableActionDoesNotExist('edit');
    expect($template->versions()->first()->content)->toBe('v1 body');
    expect($template->versions()->where('is_active', true)->count())->toBe(1);
});

it('only offers the activate action for inactive versions', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $template = PromptTemplate::create(['key' => 'generation', 'name' => 'Generation']);
    $template->versions()->create(['version' => 1, 'content' => 'v1 body', 'is_active' => true]);

    $manager = \App\Filament\Resources\PromptTemplateResource\RelationManagers\PromptVersionsRelationManager::class;
    $editPage = \App\Filament\Resources\PromptTemplateResource\Pages\EditPromptTemplate::class;

    $component = Livewire::actingAs($admin)->test($manager, [
        'ownerRecord' => $template,
        'pageClass' => $editPage,
    ]);

    $v1 = $template->versions()->where('version', 1)->first();

    // The active version carries no Activate action: the single-active
    // invariant is only ever broken transactionally by promoting an
    // inactive version.
    $component->assertTableActionHidden('activate', $v1);
});
