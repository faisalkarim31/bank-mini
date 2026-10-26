<?php

namespace App\Filament\Admin\Resources\Users\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UserStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $trend = collect(range(6, 0))->map(function ($daysAgo) {
            return User::whereDate('created_at', now()->subDays($daysAgo))->count();
        })->toArray();

        $aktif    = User::where('is_active', true)->count();
        $nonaktif = User::where('is_active', false)->count();

        return [
            Stat::make('Total Pegawai', User::count())
                ->chart($trend)
                ->color('gray'),

            Stat::make('Pegawai Aktif', $aktif)
                ->color('success')
                ->chart($this->wave($aktif)),

            Stat::make('Pegawai Nonaktif', $nonaktif)
                ->color('danger')
                ->chart($this->wave($nonaktif)),
        ];
    }

    private function wave(int|float $base, int $points = 12): array
    {
        return collect(range(0, $points - 1))
            ->map(fn ($i) => round($base + sin($i * 0.8) * 0.2, 2))
            ->all();
    }
}
