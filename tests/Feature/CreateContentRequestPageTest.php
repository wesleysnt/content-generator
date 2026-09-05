<?php

declare(strict_types=1);

use App\Filament\Pages\CreateContentRequest;
use App\Models\ContentRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('creates a request and dispatches batch jobs', function () {
    (new \Database\Seeders\PromptTemplateSeeder())->run();
    $writer = User::factory()->create(['role' => 'writer']);
    Queue::fake();

    Livewire::actingAs($writer)
        ->test(CreateContentRequest::class)
        ->fillForm([
            'topic' => 'Cloud accounting for SMBs',
            'primary_keyword' => 'cloud accounting',
            'secondary_keywords' => ['online accounting'],
            'tone' => 'professional',
            'target_persona' => 'Small business owners',
            'variation_count' => 3,
            'target_word_count' => 1500,
            'additional_instructions' => 'Be practical.',
        ])
        ->call('submit')
        ->assertRedirect();

    expect(ContentRequest::count())->toBe(1);
    Queue::assertPushed(\App\Jobs\GenerateContentJob::class, 3);
});
