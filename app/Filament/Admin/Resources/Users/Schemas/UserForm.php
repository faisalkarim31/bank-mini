<?php

namespace App\Filament\Admin\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('username')
                    ->required(),
                TextInput::make('password')
                    ->password()
                    ->required(),
                TextInput::make('full_name')
                    ->required(),
                Select::make('role')
                    ->options(['admin' => 'Admin', 'teller' => 'Teller'])
                    ->default('teller')
                    ->required(),
                Toggle::make('is_active')
                    ->required(),
                TextInput::make('created_by')
                    ->numeric(),
            ]);
    }
}
