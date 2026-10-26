<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Widgets\LatestActivities;
use App\Filament\Widgets\LatestTransactions;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\TransactionCategoryChart;
use App\Filament\Widgets\TransactionChart;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Actions\FilterAction;
use Filament\Pages\Dashboard\Concerns\HasFiltersAction;
use Illuminate\Support\Facades\Auth;

class Dashboard extends BaseDashboard
{
    use HasFiltersAction;

    protected static ?string $title = 'Dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    public function getColumns(): int | array
    {
        return [
            'default' => 1,
            'lg' => 3,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            FilterAction::make()
                ->visible(fn () => Auth::user()?->role !== 'teller')
                ->label(function ($livewire) {
                    $from = $livewire->filters['startDate'] ?? null;
                    $until = $livewire->filters['endDate'] ?? null;

                    return ($from || $until)
                        ? trim(($from ?? '...') . ' s/d ' . ($until ?? '...'))
                        : 'Semua Tanggal';
                })
                ->icon('heroicon-o-calendar')
                ->color('gray')
                ->modalHeading('Filter Tanggal')
                ->modalWidth('md')
                ->modalSubmitActionLabel('Terapkan')
                ->modalCancelActionLabel('Batalkan')
                ->schema([
                    DatePicker::make('startDate')->label('Dari Tanggal'),
                    DatePicker::make('endDate')->label('Sampai Tanggal'),
                ])
                ->extraModalFooterActions([
                    Action::make('resetTanggal')
                        ->label('Reset')
                        ->color('danger')
                        ->outlined()
                        ->visible(fn ($livewire) => filled($livewire->filters['startDate'] ?? null)
                            || filled($livewire->filters['endDate'] ?? null))
                        ->action(function ($livewire) {
                            $livewire->filters = null;
                        })
                        ->cancelParentActions(),
                ]),

            Action::make('export')
                ->label('Export')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(fn ($livewire) => $livewire->js(
                    "window.exportDashboardPdf('dashboard-" . now()->format('Ymd-His') . ".pdf')"
                )),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            StatsOverview::class,
        ];
    }

    public function getWidgets(): array
    {
        if (Auth::user()?->role === 'teller') {
            return [
                LatestTransactions::class,
                TransactionCategoryChart::class,
                LatestActivities::class,
            ];
        }

        return [
            TransactionChart::class,
            TransactionCategoryChart::class,
            LatestTransactions::class,
            LatestActivities::class,
        ];
    }
}
