<?php

declare(strict_types=1);

use App\AI\Angles\AnglePool;
use App\Enums\AngleType;

it('assigns distinct angles', function () {
    $angles = app(AnglePool::class)->assign(5, 0);

    expect($angles)->toHaveCount(5);
    expect(count(array_unique($angles, SORT_REGULAR)))->toBe(5);
});

it('rotates angles by seed', function () {
    $pool = app(AnglePool::class);

    $first = $pool->assign(1, 0);
    $second = $pool->assign(1, 1);

    expect($first[0])->not->toBe($second[0]);
});

it('caps at pool size', function () {
    $pool = app(AnglePool::class);

    expect(fn () => $pool->assign(11, 0))->toThrow(InvalidArgumentException::class);
});
