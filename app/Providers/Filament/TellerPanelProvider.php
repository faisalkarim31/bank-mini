<?php

namespace App\Providers\Filament;

use App\Filament\Support\HeadStyles;
use App\Filament\Teller\Pages\Dashboard;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class TellerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('teller')
            ->path('teller')
            ->viteTheme('resources/css/filament/teller/theme.css')
            ->login(\App\Filament\Auth\Login::class)
            ->globalSearch(false)
            ->userMenuItems([
                'logout' => Action::make('logout')->visible(false),
            ])
            ->colors([
                'primary' => Color::Orange,
            ])
            ->navigationGroups([
                NavigationGroup::make('Operasional'),
                NavigationGroup::make('Lainnya'),
            ])
            ->discoverResources(in: app_path('Filament/Teller/Resources'), for: 'App\\Filament\\Teller\\Resources')
            ->discoverPages(in: app_path('Filament/Teller/Pages'), for: 'App\\Filament\\Teller\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn () => HeadStyles::render(),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => view('filament.admin.hooks.export-script')->render(),
            )
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): string => view('filament.admin.hooks.user-info')->render(),
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_FOOTER,
                fn (): string => view('filament.admin.hooks.sidebar-logout')->render(),
            )
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
