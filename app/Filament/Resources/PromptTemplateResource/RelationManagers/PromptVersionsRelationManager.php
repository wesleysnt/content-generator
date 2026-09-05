<?php

declare(strict_types=1);

namespace App\Filament\Resources\PromptTemplateResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class PromptVersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    protected static ?string $title = 'Prompt Versions';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Textarea::make('content')->rows(20)->required(),
            Forms\Components\Toggle::make('is_active')->label('Active'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('version')
            ->columns([
                Tables\Columns\TextColumn::make('version'),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
                Tables\Columns\TextColumn::make('updated_at')->dateTime(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('New Version')
                    ->mutateFormDataUsing(function (array $data): array {
                        $max = (int) $this->getOwnerRecord()->versions()->max('version');
                        $data['version'] = $max + 1;
                        $data['is_active'] = false;

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateRecordDataUsing(function (array $data): array {
                        unset($data['version']);

                        return $data;
                    })
                    ->mutateFormDataUsing(function (array $data): array {
                        unset($data['version']);

                        return $data;
                    }),
                Tables\Actions\Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        DB::transaction(function () use ($record) {
                            $record->template->versions()->update(['is_active' => false]);
                            $record->update(['is_active' => true]);
                        });
                    }),
            ]);
    }
}
