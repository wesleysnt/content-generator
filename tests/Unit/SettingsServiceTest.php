<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('falls back to config defaults when no setting exists', function () {
    $service = app(SettingsService::class);

    expect($service->limitVariations())->toBe(config('ai.limits.max_variations'));
});

it('overrides config defaults with database values', function () {
    Setting::create(['key' => 'max_variations', 'value' => '7']);

    $service = app(SettingsService::class);

    expect($service->limitVariations())->toBe(7);
});

it('stores and retrieves settings', function () {
    $service = app(SettingsService::class);

    $service->set('max_word_count', 2500);

    expect($service->get('max_word_count', 5000))->toBe(2500);
});
