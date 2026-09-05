<?php

namespace App\Providers\Filament;

use App\Filament\Pages\ArticleEditor;
use App\Filament\Pages\CreateContentRequest;
use App\Filament\Pages\GenerationWorkspace;
use App\Filament\Pages\ListContentRequests;
use App\Filament\Pages\Settings;
use App\Filament\Resources\AiUsageLogResource;
use App\Filament\Resources\PromptTemplateResource;
use App\Filament\Resources\UserResource;
use App\Filament\Widgets\RecentContentWidget;
use App\Filament\Widgets\UsageStatsWidget;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors(['primary' => Color::Indigo])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                UsageStatsWidget::class,
                RecentContentWidget::class,
            ])
            ->pages([
                ListContentRequests::class,
                CreateContentRequest::class,
                GenerationWorkspace::class,
                ArticleEditor::class,
                Settings::class,
            ])
            ->resources([
                UserResource::class,
                PromptTemplateResource::class,
                AiUsageLogResource::class,
            ])
            ->navigationGroups([
                'Content',
                'Administration',
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
