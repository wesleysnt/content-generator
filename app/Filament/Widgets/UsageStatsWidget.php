<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\UsageService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UsageStatsWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'This Month';

    protected function getStats(): array
    {
        $stats = app(UsageService::class)->monthlyStats(now()->startOfMonth(), now()->endOfMonth());

        return [
            Stat::make('Generations', (string) $stats['generations']),
            Stat::make('Variations', (string) $stats['variations']),
            Stat::make('Input tokens', number_format($stats['input_tokens'])),
            Stat::make('Output tokens', number_format($stats['output_tokens'])),
            Stat::make('Estimated cost', '$'.number_format($stats['cost'], 2)),
        ];
    }
}
