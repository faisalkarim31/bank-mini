<?php

namespace App\Filament\Admin\Resources\Users;

use App\Filament\Admin\Resources\Users\Pages;
use App\Models\AuditLog;
use App\Models\User;
use BackedEnum;
use UnitEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-identification';
    protected static string | UnitEnum | null $navigationGroup = 'Operasional';
    protected static ?int $navigationSort = 1;
    protected static ?string $pluralModelLabel = 'Pegawai';
    protected static ?string $modelLabel = 'Pegawai';
    protected static ?string $recordTitleAttribute = 'full_name';

    public static function canAccess(): bool
    {
        return false;
    }

    public const ROLES = [
        'admin'  => 'Admin',
        'teller' => 'Teller',
    ];

    public const STATUSES = [
        'aktif'    => 'Aktif',
        'nonaktif' => 'Nonaktif',
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Forms\Components\TextInput::make('employee_id')
                    ->label('ID Pegawai')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),

                Forms\Components\TextInput::make('full_name')
                    ->label('Nama Lengkap')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('role')
                    ->label('Peran')
                    ->options(self::ROLES)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->searchPlaceholder('Cari pengguna...')
            ->toolbarActions([
                self::filterDropdown('roleFilter', 'Semua Peran', self::ROLES),
                self::filterDropdown('statusFilter', 'Semua Status', self::STATUSES),
            ])
            ->modifyQueryUsing(fn (Builder $query, $livewire) => $query
                ->when($livewire->roleFilter ?? null, fn ($q, $role) => $q->where('role', $role))
                ->when($livewire->statusFilter ?? null, fn ($q, $status) => $q->where('is_active', $status === 'aktif')))
            ->paginated([10, 25, 50])
            ->columns([
                Tables\Columns\TextColumn::make('employee_id')
                    ->label('ID Pegawai')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('full_name')
                    ->label('Nama Lengkap')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('role')
                    ->label('Peran')
                    ->badge()
                    ->formatStateUsing(fn ($state) => self::ROLES[$state] ?? ucfirst($state))
                    ->color(fn ($state) => match ($state) {
                        'admin'  => 'warning',
                        'teller' => 'info',
                        default  => 'gray',
                    }),

                Tables\Columns\TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'Aktif' : 'Nonaktif')
                    ->color(fn ($state) => $state ? 'success' : 'danger')
                    ->icon(fn ($state) => $state ? 'heroicon-m-check-circle' : 'heroicon-m-x-circle'),
            ])
            ->defaultSort('employee_id')
            ->recordActions([
                EditAction::make()
                    ->label('Edit')
                    ->modalHeading('Edit Pegawai')
                    ->modalWidth('md'),

                Action::make('toggleStatus')
                    ->label(fn (User $record) => $record->is_active ? 'Nonaktifkan' : 'Aktifkan')
                    ->icon(fn (User $record) => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (User $record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->hidden(fn (User $record) => $record->id === Auth::id())
                    ->action(function (User $record) {
                        $wasActive = $record->is_active;
                        $record->update(['is_active' => ! $wasActive]);

                        AuditLog::record(
                            $wasActive ? 'Nonaktifkan Pegawai' : 'Aktifkan Pegawai',
                            $record,
                            $record->full_name
                        );

                        Notification::make()
                            ->title($wasActive ? 'Pegawai dinonaktifkan' : 'Pegawai diaktifkan')
                            ->success()
                            ->send();
                    }),
            ]);
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
            'index' => Pages\ListUsers::route('/'),
        ];
    }
}
