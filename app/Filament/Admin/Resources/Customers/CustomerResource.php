<?php

namespace App\Filament\Admin\Resources\Customers;

use App\Filament\Admin\Resources\Customers\Pages;
use App\Filament\Admin\Resources\Customers\RelationManagers\TransactionsRelationManager;
use App\Models\Customer;
use App\Models\AuditLog;
use App\Enums\CustomerCategory;
use BackedEnum;
use UnitEnum;
use Filament\Schemas\Components\Section;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Support\Enums\Alignment;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Support\Facades\Auth;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-users';
    protected static string | UnitEnum | null $navigationGroup = 'Operasional';

    protected static ?string $pluralModelLabel = 'Nasabah';
    protected static ?string $modelLabel = 'Nasabah';
    protected static ?string $navigationLabel = 'Nasabah';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Forms\Components\TextInput::make('full_name')
                    ->label('Nama Lengkap')
                    ->required(),

                Forms\Components\Select::make('category')
                    ->label('Kategori')
                    ->options(CustomerCategory::options())
                    ->required(),
            ]);
    }

    protected static function filterDropdown(string $property, string $allLabel, array $options): ActionGroup
    {
        $actions = [
            Action::make("{$property}_all")
                ->label($allLabel)
                ->action(function ($livewire) use ($property) {
                    $livewire->{$property} = null;
                    $livewire->resetPage();
                }),
        ];

        foreach ($options as $value => $label) {
            $actions[] = Action::make("{$property}_{$value}")
                ->label($label)
                ->action(function ($livewire) use ($property, $value) {
                    $livewire->{$property} = (string) $value;
                    $livewire->resetPage();
                });
        }

        return ActionGroup::make($actions)
            ->button()
            ->label(fn ($livewire) => $options[$livewire->{$property}] ?? $allLabel)
            ->icon('heroicon-m-chevron-down')
            ->iconPosition('after')
            ->color('gray');
    }

    public static function table(Table $table): Table
    {
        $categoryOptions = collect(CustomerCategory::cases())
            ->mapWithKeys(fn (CustomerCategory $c) => [$c->value => $c->label()])
            ->all();

        return $table
            ->searchPlaceholder('Cari nama/rekening...')
            ->toolbarActions([
                static::filterDropdown('categoryFilter', 'Semua Kategori', $categoryOptions),
                static::filterDropdown('statusFilter', 'Semua Status', [
                    'aktif' => 'Aktif',
                    'nonaktif' => 'Nonaktif',
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query, $livewire) => $query
                ->when($livewire->categoryFilter ?? null, fn ($q, $c) => $q->where('category', $c))
                ->when($livewire->statusFilter ?? null, fn ($q, $s) => $q->where('is_active', $s === 'aktif')))
            ->paginated([10, 25, 50])
            ->columns([
                Tables\Columns\TextColumn::make('account_number')
                    ->label('No. Rekening')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category')
                    ->label('Kategori')
                    ->badge()
                    ->formatStateUsing(fn (CustomerCategory $state) => $state->label())
                    ->color(fn (CustomerCategory $state) => $state->color()),
                Tables\Columns\TextColumn::make('balance')
                    ->label('Saldo')
                    ->money('IDR', locale: 'id', decimalPlaces: 0),
                Tables\Columns\TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Aktif' : 'Nonaktif')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->icon(fn (bool $state): string => $state ? 'heroicon-m-check-circle' : 'heroicon-m-x-circle'),
            ])
            ->actions([
                ViewAction::make()->label('Detail'),

                EditAction::make()
                    ->label('Edit')
                    ->modalHeading('Edit Nasabah')
                    ->modalWidth('sm')
                    ->modalSubmitActionLabel('Simpan')
                    ->modalCancelActionLabel('Batalkan')
                    ->modalAlignment(Alignment::Start)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->after(fn (Customer $record) => AuditLog::record('Edit Nasabah', $record, $record->full_name)),

                Action::make('toggleActive')
                    ->visible(fn () => Auth::user()?->role === 'admin')
                    ->label(fn (Customer $record): string => $record->is_active ? 'Nonaktifkan' : 'Aktifkan')
                    ->icon(fn (Customer $record): string => $record->is_active
                        ? 'heroicon-o-x-circle'
                        : 'heroicon-o-check-circle')
                    ->color(fn (Customer $record): string => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function (Customer $record): void {
                        $wasActive = $record->is_active;
                        $record->update(['is_active' => ! $wasActive]);

                        AuditLog::record(
                            $wasActive ? 'Nonaktifkan Nasabah' : 'Aktifkan Nasabah',
                            $record,
                            $record->full_name
                        );
                    }),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->columns(2)
                    ->components([
                        TextEntry::make('full_name')
                            ->label('Nama Lengkap'),

                        TextEntry::make('balance')
                            ->label('Total Saldo')
                            ->money('IDR', locale: 'id', decimalPlaces: 0),

                        TextEntry::make('category')
                            ->label('Kategori')
                            ->badge()
                            ->formatStateUsing(fn (CustomerCategory $state) => $state->label())
                            ->color(fn (CustomerCategory $state) => $state->color()),

                        TextEntry::make('is_active')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state ? 'Aktif' : 'Nonaktif')
                            ->color(fn ($state) => $state ? 'success' : 'danger')
                            ->icon(fn ($state) => $state ? 'heroicon-m-check-circle' : 'heroicon-m-x-circle'),

                        TextEntry::make('account_number')
                            ->label('Nomor Rekening'),

                        TextEntry::make('created_at')
                            ->label('Tanggal Dibuat')
                            ->date('d F Y'),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'view' => Pages\ViewCustomer::route('/{record}'),
        ];
    }
}
