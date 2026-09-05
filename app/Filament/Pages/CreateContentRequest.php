<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Pages\GenerationWorkspace;
use App\Services\GenerationService;
use App\Services\SettingsService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

class CreateContentRequest extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';

    protected static ?string $navigationGroup = 'Content';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.create-content-request';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'tone' => 'professional',
            'variation_count' => 3,
            'target_word_count' => 1500,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('topic')->label('Topic / Title')->required()->maxLength(500),
                TextInput::make('primary_keyword')->label('Primary Keyword')->required()->maxLength(200),
                TagsInput::make('secondary_keywords')->label('Secondary Keywords')->separator(',')->nestedRecursiveRules(['string']),
                Select::make('tone')
                    ->options([
                        'professional' => 'Professional',
                        'professional-approachable' => 'Professional & Approachable',
                        'conversational' => 'Conversational',
                        'authoritative' => 'Authoritative',
                        'friendly' => 'Friendly',
                    ])
                    ->required(),
                TextInput::make('target_persona')->label('Target Audience')->maxLength(200),
                Select::make('variation_count')
                    ->label('Variations')
                    ->options([1 => '1', 3 => '3', 5 => '5', 10 => '10'])
                    ->required(),
                TextInput::make('target_word_count')
                    ->label('Target Word Count')
                    ->numeric()
                    ->minValue(100)
                    ->maxValue(fn () => app(SettingsService::class)->limitWordCount())
                    ->required(),
                Textarea::make('additional_instructions')->rows(4)->maxLength(2000),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        // Filament v3.3 TagsInput with a separator() round-trips its state
        // between Livewire requests as a joined string (see TagsInput::setUp);
        // normalise that back to an array for GenerationService, which
        // validates 'secondary_keywords' as an array.
        if (isset($data['secondary_keywords']) && is_string($data['secondary_keywords'])) {
            $data['secondary_keywords'] = collect(explode(',', $data['secondary_keywords']))
                ->map(fn (string $tag) => trim($tag))
                ->filter(fn (string $tag) => $tag !== '')
                ->values()
                ->all();
        }

        $service = app(GenerationService::class);
        $request = $service->createRequest(auth()->user(), $data);
        $service->dispatchBatch($request);

        // The brief redirects to GenerationWorkspace, which is Task 22 and
        // does not exist yet. Redirect there once it lands; until then, send
        // the user to the content list where the new request is visible.
        $this->redirect(
            class_exists(GenerationWorkspace::class)
                ? GenerationWorkspace::getUrl(['record' => $request->id])
                : ListContentRequests::getUrl()
        );
    }
}
