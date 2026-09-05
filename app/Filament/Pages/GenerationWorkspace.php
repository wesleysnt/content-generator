<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\RequestStatus;
use App\Enums\VariationStatus;
use App\Models\ContentRequest;
use App\Services\GenerationService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;

class GenerationWorkspace extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';

    protected static ?string $navigationGroup = 'Content';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'content/{record}/workspace';

    protected static string $view = 'filament.pages.generation-workspace';

    public $record;

    public function mount(ContentRequest $record): void
    {
        abort_unless(Gate::allows('view', $record), 403);

        $this->record = $record->load(['user', 'variations' => fn ($q) => $q->with('sections')]);
    }

    public function refreshRecord(): void
    {
        $this->record = $this->record->fresh(['user', 'variations' => fn ($q) => $q->with('sections')]);
    }

    public function lock(int $variationId): void
    {
        app(GenerationService::class)->lock($variationId);
        $this->refreshRecord();
    }

    public function unlock(int $variationId): void
    {
        app(GenerationService::class)->unlock($variationId);
        $this->refreshRecord();
    }

    public function discard(int $variationId): void
    {
        app(GenerationService::class)->discard($variationId);
        $this->refreshRecord();
    }

    public function regenerateVariation(int $variationId): void
    {
        \App\Jobs\RegenerateContentJob::dispatch($variationId);

        Notification::make()->title('Regeneration queued.')->success()->send();
        $this->refreshRecord();
    }

    public function regenerateUnlocked(): void
    {
        app(GenerationService::class)->regenerateUnlocked($this->record);

        Notification::make()->title('Regeneration queued for unlocked variations.')->success()->send();
        $this->refreshRecord();
    }

    protected function getViewData(): array
    {
        return [
            'record' => $this->record,
            'isProcessing' => in_array($this->record->status, [RequestStatus::Queued, RequestStatus::Processing]),
            'hasUnlocked' => $this->record->variations->contains(fn ($v) => $v->isRegenerable()),
        ];
    }
}
