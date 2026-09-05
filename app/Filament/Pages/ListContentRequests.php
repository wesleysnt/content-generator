<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\RequestStatus;
use App\Models\ContentRequest;
use Filament\Actions\Action;
use Filament\Pages\Page;

class ListContentRequests extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Content';

    protected static string $view = 'filament.pages.list-content-requests';

    public string $statusFilter = '';

    public function mount(): void
    {
        if (request()->has('status')) {
            $this->statusFilter = (string) request()->string('status');
        }
    }

    public function updatedStatusFilter(): void
    {
        $this->redirectRoute(
            'filament.admin.pages.list-content-requests',
            $this->statusFilter ? ['status' => $this->statusFilter] : []
        );
    }

    protected function getViewData(): array
    {
        $query = ContentRequest::query()
            ->with(['user', 'variations'])
            ->latest();

        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        return [
            'requests' => $query->paginate(20),
            'statuses' => collect(RequestStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->value]),
        ];
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('new')
                ->label('New Content')
                ->url(CreateContentRequest::getUrl()),
        ];
    }
}
