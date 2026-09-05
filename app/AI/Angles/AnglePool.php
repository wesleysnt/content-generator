<?php

declare(strict_types=1);

namespace App\AI\Angles;

use App\Enums\AngleType;
use InvalidArgumentException;

class AnglePool
{
    public function assign(int $count, int $seed): array
    {
        $all = AngleType::cases();

        if ($count > count($all)) {
            throw new InvalidArgumentException("Cannot assign {$count} angles from pool of ".count($all));
        }

        $offset = $seed % count($all);

        return array_slice(array_merge(
            array_slice($all, $offset),
            array_slice($all, 0, $offset)
        ), 0, $count);
    }
}
