<?php

namespace App\Filament\Admin\Resources\Customers\Schemas;

use App\Enums\CustomerCategory;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('account_number')
                    ->required(),
                TextInput::make('full_name')
                    ->required(),
                Select::make('category')
                    ->options(CustomerCategory::class)
                    ->required(),
                TextInput::make('balance')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                Toggle::make('is_active')
                    ->required(),
                TextInput::make('created_by')
                    ->required()
                    ->numeric(),
            ]);
    }
}
