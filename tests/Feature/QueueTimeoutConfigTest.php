<?php

// tests/Feature/QueueTimeoutConfigTest.php
//
// Regression guard for the end-to-end smoke run finding: Horizon's per-job
// worker timeout (60s default) killed real DeepSeek generations mid-call,
// leaving requests stuck in "processing" and variations in "pending" with
// no error. The AI provider allows up to config('ai.timeout') seconds, so
// the queue worker must allow at least that long per job.

it('horizon worker timeout is not lower than the AI provider timeout', function () {
    $horizonTimeout = (int) (config('horizon.defaults.supervisor-1.timeout') ?? 60);
    $aiTimeout = (int) config('ai.timeout', 300);

    expect($horizonTimeout)
        ->toBeGreaterThanOrEqual($aiTimeout)
        ->and(config('horizon.defaults.supervisor-1.connection'))
        ->toBe('redis');
});

it('regeneration jobs declare retries so transient provider failures recover', function () {
    $jobs = [
        new \App\Jobs\GenerateContentJob(1),
        new \App\Jobs\RegenerateContentJob(1),
        new \App\Jobs\RegenerateTitleJob(1),
    ];
    foreach ($jobs as $job) {
        expect($job->tries)->toBeGreaterThan(1)
            ->and($job->backoff)->not->toBeEmpty();
    }
});
