<?php
// tests/Feature/SettingsPageTest.php

use App\Filament\Pages\Settings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('allows admin to save limits', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->fillForm([
            'max_variations' => 7,
            'max_word_count' => 3000,
            'max_monthly_generations' => 50,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(\App\Services\SettingsService::class)->limitVariations())->toBe(7);
});
