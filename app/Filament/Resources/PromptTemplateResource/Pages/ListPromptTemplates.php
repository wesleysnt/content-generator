<?php

declare(strict_types=1);

namespace App\Filament\Resources\PromptTemplateResource\Pages;

use App\Filament\Resources\PromptTemplateResource;
use Filament\Resources\Pages\ListRecords;

class ListPromptTemplates extends ListRecords
{
    protected static string $resource = PromptTemplateResource::class;
}
