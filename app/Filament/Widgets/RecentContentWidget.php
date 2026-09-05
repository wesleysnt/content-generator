<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\ContentRequest;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentContentWidget extends TableWidget
{
    protected static ?string $heading = 'Recent Content';

    public function table(Table $table): Table
    {
        return $table
            ->query(ContentRequest::query()->latest()->limit(5))
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('Request'),
                Tables\Columns\TextColumn::make('topic')->limit(40),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
            ]);
    }
}
