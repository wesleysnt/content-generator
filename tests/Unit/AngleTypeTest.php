<?php

use App\Enums\AngleType;

it('has exactly ten angles', function () {
    expect(count(AngleType::cases()))->toBe(10);
});

it('has distinct labels', function () {
    $labels = array_map(fn ($a) => $a->label(), AngleType::cases());
    expect(count(array_unique($labels)))->toBe(10);
});
