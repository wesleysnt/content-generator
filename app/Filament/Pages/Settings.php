<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Services\SettingsService;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class Settings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Administration';

    protected static string $view = 'filament.pages.settings';

    public ?array $data = [];

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $service = app(SettingsService::class);

        $this->form->fill([
            'max_variations' => $service->limitVariations(),
            'max_word_count' => $service->limitWordCount(),
            'max_monthly_generations' => $service->limitMonthlyGenerations(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('max_variations')->numeric()->minValue(1)->maxValue(10)->required(),
                TextInput::make('max_word_count')->numeric()->minValue(100)->maxValue(10000)->required(),
                TextInput::make('max_monthly_generations')->numeric()->minValue(1)->required(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $service = app(SettingsService::class);
        $service->set('max_variations', $data['max_variations']);
        $service->set('max_word_count', $data['max_word_count']);
        $service->set('max_monthly_generations', $data['max_monthly_generations']);

        Notification::make()->title('Settings saved.')->success()->send();
    }
}
