<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\AppliesDateFilter;
use App\Models\AuditLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class LatestActivities extends TableWidget
{
    use AppliesDateFilter;

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 4;

    public static function canView(): bool
    {
        return Auth::user()?->role === 'admin';
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Aktivitas Terbaru')
            ->query(fn (): Builder => $this->applyDateFilter(AuditLog::query()->with('user')))
            ->defaultSort('created_at', 'desc')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y, H:i'),

                TextColumn::make('user.full_name')
                    ->label('Pengguna'),

                TextColumn::make('user.role')
                    ->label('Peran')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'admin' => 'Admin',
                        'teller' => 'Teller',
                        default => '-',
                    })
                    ->color(fn ($state) => match ($state) {
                        'admin' => 'warning',
                        'teller' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('action')
                    ->label('Aksi'),

                TextColumn::make('target_name')
                    ->label('Target')
                    ->default('-'),
            ]);
    }
}
