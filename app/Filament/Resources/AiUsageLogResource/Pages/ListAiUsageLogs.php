<?php

declare(strict_types=1);

namespace App\Filament\Resources\AiUsageLogResource\Pages;

use App\Filament\Resources\AiUsageLogResource;
use Filament\Resources\Pages\ListRecords;

class ListAiUsageLogs extends ListRecords
{
    protected static string $resource = AiUsageLogResource::class;
}
