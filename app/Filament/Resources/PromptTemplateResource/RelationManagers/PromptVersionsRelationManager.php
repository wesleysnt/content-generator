<?php

declare(strict_types=1);

namespace App\Filament\Resources\PromptTemplateResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class PromptVersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    protected static ?string $title = 'Prompt Versions';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('version')
            ->columns([
                Tables\Columns\TextColumn::make('version'),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
                Tables\Columns\TextColumn::make('content')->limit(60),
                Tables\Columns\TextColumn::make('updated_at')->dateTime(),
            ])
            ->headerActions([
                // Versions are immutable once created: writers of later
                // versions must create a new version rather than silently
                // rewriting history (already-generated content points at a
                // prompt_version_id). A create-only form is the only place
                // content can be set, and a version is only promoted through
                // the transactional Activate action.
                Tables\Actions\CreateAction::make()
                    ->label('New Version')
                    ->form([
                        Forms\Components\Textarea::make('content')->label('Prompt Content')->rows(20)->required(),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $max = (int) $this->getOwnerRecord()->versions()->max('version');
                        $data['version'] = $max + 1;
                        $data['is_active'] = false;

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->visible(fn ($record): bool => ! $record->is_active)
                    ->action(function ($record): void {
                        DB::transaction(function () use ($record) {
                            $record->template->versions()->update(['is_active' => false]);
                            $record->update(['is_active' => true]);
                        });
                    }),
            ]);
    }
}
