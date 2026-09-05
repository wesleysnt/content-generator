<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\RevisionType;
use App\Enums\VariationStatus;
use App\Jobs\RegenerateSectionJob;
use App\Jobs\RegenerateTitleJob;
use App\Models\ContentVariation;
use App\Services\GenerationService;
use App\Services\RevisionService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Mews\Purifier\Facades\Purifier;

class ArticleEditor extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-pencil-square';

    protected static ?string $navigationGroup = 'Content';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'articles/{record}/edit';

    protected static string $view = 'filament.pages.article-editor';

    #[Locked]
    public $record;

    public array $formState = [];

    public array $sectionState = [];

    public function mount(ContentVariation $record): void
    {
        Gate::authorize('view', $record);

        $this->record = $record->load(['request', 'sections', 'revisions']);

        $this->formState = $record->only([
            'title', 'slug', 'excerpt', 'meta_title', 'meta_description',
            'focus_keyword', 'secondary_keywords', 'category', 'tags',
            'og_title', 'og_description', 'schema_type',
        ]);

        $this->sectionState = $record->sections->keyBy('id')->map->only(['heading', 'body'])->toArray();
    }

    public function save(): void
    {
        Gate::authorize('update', $this->record);

        $fields = [
            'title', 'slug', 'excerpt', 'meta_title', 'meta_description',
            'focus_keyword', 'secondary_keywords', 'category', 'tags',
            'og_title', 'og_description', 'schema_type',
        ];

        $clean = fn (array $section): array => [
            'heading' => $section['heading'],
            'body' => Purifier::clean($section['body']),
        ];

        $sectionsUnchanged = collect($this->sectionState)->map($clean)->all()
            === $this->record->sections()->get()->keyBy('id')->map->only(['heading', 'body'])->map($clean)->all();

        // Saving without edits used to write a no-op WriterEdit snapshot into
        // the revision history on every visit; skip the write entirely.
        if ($this->formState === $this->record->only($fields) && $sectionsUnchanged) {
            return;
        }

        app(RevisionService::class)->snapshot($this->record, RevisionType::WriterEdit, auth()->id());

        $this->record->update([
            'title' => $this->formState['title'],
            'slug' => $this->formState['slug'],
            'excerpt' => $this->formState['excerpt'],
            'meta_title' => $this->formState['meta_title'],
            'meta_description' => $this->formState['meta_description'],
            'focus_keyword' => $this->formState['focus_keyword'],
            'secondary_keywords' => $this->formState['secondary_keywords'] ?? [],
            'category' => $this->formState['category'],
            'tags' => $this->formState['tags'] ?? [],
            'og_title' => $this->formState['og_title'],
            'og_description' => $this->formState['og_description'],
            'schema_type' => $this->formState['schema_type'],
        ]);

        foreach ($this->sectionState as $id => $state) {
            $this->record->sections()->find($id)?->update([
                'heading' => $state['heading'],
                'body' => Purifier::clean($state['body']),
            ]);
        }

        Notification::make()->title('Saved.')->success()->send();
    }

    public function regenerateSection(int $sectionId): void
    {
        Gate::authorize('regenerate', $this->record);
        $this->record->sections()->findOrFail($sectionId);

        RegenerateSectionJob::dispatch($this->record->id, $sectionId, auth()->id());

        Notification::make()->title('Section regeneration queued.')->success()->send();
    }

    public function regenerateTitle(): void
    {
        Gate::authorize('regenerate', $this->record);

        RegenerateTitleJob::dispatch($this->record->id, auth()->id());

        Notification::make()->title('Title regeneration queued.')->success()->send();
    }

    public function restoreRevision(int $revisionId): void
    {
        Gate::authorize('update', $this->record);

        $revision = $this->record->revisions()->findOrFail($revisionId);

        app(RevisionService::class)->restore($this->record, $revision, auth()->id());

        $this->mount($this->record->fresh(['request', 'sections', 'revisions']));

        Notification::make()->title('Revision restored.')->success()->send();
    }

    public function markFinal(): void
    {
        Gate::authorize('update', $this->record);

        app(GenerationService::class)->markFinal($this->record->id, auth()->user());

        Notification::make()->title('Marked as final.')->success()->send();
    }

    protected function getViewData(): array
    {
        return [
            'record' => $this->record->fresh(['request', 'sections', 'revisions']),
            'isLocked' => $this->record->is_locked,
            'isFinal' => $this->record->status === VariationStatus::Final,
        ];
    }
}
