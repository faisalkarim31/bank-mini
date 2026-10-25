<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CustomerStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $trend = collect(range(6, 0))->map(function ($daysAgo) {
            return Customer::whereDate('created_at', now()->subDays($daysAgo))->count();
        })->toArray();

        return [
            Stat::make('Total Nasabah', Customer::count())
                ->chart($trend)
                ->color('gray'),

            Stat::make('Nasabah Aktif', Customer::where('is_active', true)->count())
                ->color('success')
                ->chart([1, 2, 1, 3, 2, 4, Customer::where('is_active', true)->count()]), // placeholder visual

            Stat::make('Nasabah Nonaktif', Customer::where('is_active', false)->count())
                ->color('danger')
                ->chart([0, 1, 0, 1, 1, 0, Customer::where('is_active', false)->count()]), // placeholder visual
        ];
    }
}
