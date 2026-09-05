<?php

declare(strict_types=1);

use App\Models\AiUsageLog;
use App\Models\ContentRequest;
use App\Models\ContentVariation;
use App\Models\User;
use App\Services\UsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('computes cost from configurable pricing', function () {
    $cost = app(UsageService::class)->cost(1_000_000, 1_000_000);

    expect($cost)->toBe(0.28 + 0.42);
});

it('records a usage log row', function () {
    $user = User::factory()->create();
    $request = ContentRequest::create([
        'user_id' => $user->id,
        'topic' => 'T',
        'primary_keyword' => 'kw',
    ]);

    $log = app(UsageService::class)->record(
        $user, $request, null, 'deepseek-v4-pro', 'generation', 100, 200, 1500, 'success'
    );

    expect($log->total_tokens)->toBe(300);
    expect(AiUsageLog::count())->toBe(1);
});

it('aggregates monthly stats', function () {
    $user = User::factory()->create();
    $request = ContentRequest::create([
        'user_id' => $user->id,
        'topic' => 'T',
        'primary_keyword' => 'kw',
    ]);
    $variation = ContentVariation::create([
        'content_request_id' => $request->id,
        'variation_number' => 1,
        'angle_type' => 'educational',
    ]);

    $service = app(UsageService::class);
    $service->record($user, $request, $variation, 'deepseek-v4-pro', 'generation', 1000, 1000, 10, 'success');

    $stats = $service->monthlyStats(now()->startOfMonth(), now()->endOfMonth());

    expect($stats['generations'])->toBe(1);
    expect($stats['variations'])->toBe(1);
    expect($stats['input_tokens'])->toBe(1000);
    expect($stats['output_tokens'])->toBe(1000);
    expect($stats['cost'])->toBeGreaterThan(0);
});
