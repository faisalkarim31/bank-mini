<?php

namespace App\Filament\Admin\Resources\Transactions\Schemas;

use App\Enums\TransactionType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('customer_id')
                    ->required()
                    ->numeric(),
                TextInput::make('transaction_code')
                    ->required(),
                Select::make('type')
                    ->options(TransactionType::class)
                    ->required(),
                TextInput::make('amount')
                    ->required()
                    ->numeric(),
                TextInput::make('processed_by')
                    ->required()
                    ->numeric(),
            ]);
    }
}
