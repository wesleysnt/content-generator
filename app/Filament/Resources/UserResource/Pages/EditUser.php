<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->hidden(fn (): bool => (int) $this->record->getKey() === (int) auth()->id()),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (filled($data['password'] ?? null)) {
            $data['password'] = bcrypt($data['password']);
        } else {
            unset($data['password']);
        }

        // Demoting yourself is a footgun: the only admin left in the panel
        // could lock everyone out of user administration.
        if ((int) $this->record->getKey() === (int) auth()->id()) {
            $data['role'] = $this->record->role;
        }

        return $data;
    }
}
