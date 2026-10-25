<?php

namespace App\Filament\Admin\Resources\Customers\RelationManagers;

use App\Enums\TransactionType;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'Riwayat Transaksi';

    public ?string $dateFrom = null;
    public ?string $dateUntil = null;
    public ?string $typeFilter = null;

    #[On('transaction-filters-updated')]
    public function applyFilters($from = null, $until = null, $type = null): void
    {
        $this->dateFrom = $from;
        $this->dateUntil = $until;
        $this->typeFilter = $type;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('transaction_code')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                TextColumn::make('transaction_code')
                    ->label('ID Transaksi'),

                TextColumn::make('processed_by')
                    ->label('Diproses Oleh'),

                TextColumn::make('amount')
                    ->label('Nominal')
                    ->money('IDR', locale: 'id', decimalPlaces: 0),

                TextColumn::make('type')
                    ->label('Jenis Transaksi')
                    ->badge()
                    ->formatStateUsing(fn (TransactionType $state) => $state->label())
                    ->color(fn (TransactionType $state) => $state->color()),
            ])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->when($this->dateFrom, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                ->when($this->dateUntil, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
                ->when($this->typeFilter, fn ($q, $t) => $q->where('type', $t)))
            ->headerActions([])
            ->recordActions([]);
    }
}
