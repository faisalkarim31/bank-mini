<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LogoutResponse::class, function () {
            return new class implements LogoutResponse {
                public function toResponse($request)
                {
                    return redirect('/');
                }
            };
        });
    }

    public function boot(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::STYLES_AFTER,
            fn (): HtmlString => new HtmlString(<<<'HTML'
                <style>
                    .fi-ta-header-toolbar {
                        flex-direction: row-reverse !important;
                        justify-content: flex-end !important;
                        gap: 0.75rem;
                    }
                    .fi-ta-header-toolbar > * {
                        margin-inline-start: 0 !important;
                    }
                </style>
            HTML),
            scopes: [],
        );
    }
}
