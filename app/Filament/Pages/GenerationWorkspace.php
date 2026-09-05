<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\RequestStatus;
use App\Enums\VariationStatus;
use App\Models\ContentRequest;
use App\Models\ContentVariation;
use App\Services\GenerationService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;

class GenerationWorkspace extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';

    protected static ?string $navigationGroup = 'Content';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'content/{record}/workspace';

    protected static string $view = 'filament.pages.generation-workspace';

    #[Locked]
    public $record;

    public function mount(ContentRequest $record): void
    {
        Gate::authorize('view', $record);

        $this->record = $record->load(['user', 'variations' => fn ($q) => $q->with('sections')]);
    }

    public function refreshRecord(): void
    {
        $this->record = $this->record->fresh(['user', 'variations' => fn ($q) => $q->with('sections')]);
    }

    public function lock(int $variationId): void
    {
        $this->authorizeVariation($variationId, 'lock');

        app(GenerationService::class)->lock($variationId, auth()->user());
        $this->refreshRecord();
    }

    public function unlock(int $variationId): void
    {
        $this->authorizeVariation($variationId, 'update');

        app(GenerationService::class)->unlock($variationId, auth()->user());
        $this->refreshRecord();
    }

    public function discard(int $variationId): void
    {
        $this->authorizeVariation($variationId, 'update');

        app(GenerationService::class)->discard($variationId, auth()->user());
        $this->refreshRecord();
    }

    public function regenerateVariation(int $variationId): void
    {
        $variation = $this->authorizeVariation($variationId, 'regenerate');

        \App\Jobs\RegenerateContentJob::dispatch($variationId, auth()->id());

        Notification::make()->title('Regeneration queued.')->success()->send();
        $this->refreshRecord();
    }

    public function retryVariation(int $variationId): void
    {
        $variation = $this->authorizeVariation($variationId, 'regenerate');

        // A failed initial generation (variation still pending) restarts the
        // batch generation job; a failed regeneration of already-generated
        // content re-runs the regeneration job.
        if ($variation->status === VariationStatus::Pending && $variation->error_message !== null) {
            \App\Jobs\GenerateContentJob::dispatch($variationId);
        } else {
            \App\Jobs\RegenerateContentJob::dispatch($variationId, auth()->id());
        }

        Notification::make()->title('Retry queued.')->success()->send();
        $this->refreshRecord();
    }

    public function regenerateUnlocked(): void
    {
        Gate::authorize('generate', $this->record);

        app(GenerationService::class)->regenerateUnlocked($this->record, auth()->user());

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

    private function authorizeVariation(int $variationId, string $ability): ContentVariation
    {
        // Containment: variation ids arrive from the client; only variations
        // of the mounted request may be touched (404 keeps other requests'
        // ids unguessable).
        $variation = $this->record->variations()->findOrFail($variationId);

        Gate::authorize($ability, $variation);

        return $variation;
    }
}
