<?php

namespace App\Filament\Widgets;

use App\Enums\TransactionType;
use App\Filament\Widgets\Concerns\AppliesDateFilter;
use App\Models\Transaction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LatestTransactions extends TableWidget
{
    use AppliesDateFilter;

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'lg' => 2,
    ];

    protected static ?int $sort = 1;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Transaksi Terbaru')
            ->query(fn (): Builder => $this->applyDateFilter(Transaction::query()->with('customer')))
            ->defaultSort('created_at', 'desc')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y, H:i'),

                TextColumn::make('customer.full_name')
                    ->label('Nasabah'),

                TextColumn::make('amount')
                    ->label('Nominal')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format((int) $state, 0, ',', '.')),

                TextColumn::make('type')
                    ->label('Kategori')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof TransactionType ? $state->label() : $state)
                    ->color(fn ($state) => $state instanceof TransactionType ? $state->color() : 'gray'),
            ]);
    }
}
