<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AiUsageLogResource\Pages;
use App\Models\AiUsageLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AiUsageLogResource extends Resource
{
    protected static ?string $model = AiUsageLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Administration';

    public static function table(Table $table): Table
    {
        return $table
            ->query(AiUsageLog::query()->with(['user', 'variation'])->latest())
            ->columns([
                Tables\Columns\TextColumn::make('id'),
                Tables\Columns\TextColumn::make('user.name')->label('Writer'),
                Tables\Columns\TextColumn::make('model'),
                Tables\Columns\TextColumn::make('operation')->badge(),
                Tables\Columns\TextColumn::make('input_tokens')->label('In'),
                Tables\Columns\TextColumn::make('output_tokens')->label('Out'),
                Tables\Columns\TextColumn::make('estimated_cost')->money('USD'),
                Tables\Columns\TextColumn::make('duration_ms')->label('Duration (ms)'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('operation')
                    ->options([
                        'generation' => 'Generation',
                        'regeneration' => 'Regeneration',
                        'section_regeneration' => 'Section Regeneration',
                        'title_regeneration' => 'Title Regeneration',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options(['success' => 'Success', 'failed' => 'Failed']),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAiUsageLogs::route('/'),
        ];
    }
}
