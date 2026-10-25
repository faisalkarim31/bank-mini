<?php

namespace App\Filament\Admin\Resources\AuditLogs;

use App\Filament\Admin\Resources\AuditLogs\Pages\ManageAuditLogs;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\AuditLog;
use BackedEnum;
use UnitEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string | UnitEnum | null $navigationGroup = 'Monitoring';
    protected static ?string $pluralModelLabel = 'Riwayat Aktivitas';
    protected static ?string $modelLabel = 'Aktivitas';
    protected static ?string $navigationLabel = 'Riwayat Aktivitas';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->searchPlaceholder('Cari nama/aktivitas...')
            // Dropdown Peran, sama seperti di tabel Pegawai
            ->toolbarActions([
                self::filterDropdown('roleFilter', 'Semua Peran', UserResource::ROLES),
            ])
            // Filter peran (dropdown) dan tanggal (tombol di header halaman)
            ->modifyQueryUsing(fn (Builder $query, $livewire) => $query
                ->when($livewire->roleFilter ?? null, fn ($q, $role) => $q->whereHas('user', fn ($u) => $u->where('role', $role)))
                ->when($livewire->dateFrom ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                ->when($livewire->dateUntil ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d)))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                TextColumn::make('user.full_name')
                    ->label('Pengguna')
                    ->searchable(),

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
                    ->label('Aksi')
                    ->searchable(),

                TextColumn::make('target_name')
                    ->label('Target')
                    ->default('-'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([]);
    }

    protected static function filterDropdown(string $property, string $placeholder, array $options): ActionGroup
    {
        return ActionGroup::make(
            collect(['' => $placeholder, ...$options])
                ->map(fn (string $label, string $value) => Action::make($property . '_' . ($value ?: 'all'))
                    ->label($label)
                    ->action(function ($livewire) use ($property, $value) {
                        $livewire->{$property} = $value ?: null;
                        $livewire->resetPage();
                    }))
                ->values()
                ->all()
        )
            ->button()
            ->label(fn ($livewire) => $options[$livewire->{$property} ?? ''] ?? $placeholder)
            ->icon('heroicon-m-chevron-down')
            ->iconPosition('after')
            ->color('gray');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAuditLogs::route('/'),
        ];
    }
}
