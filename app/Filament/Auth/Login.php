<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Models\AuditLog;

class Login extends BaseLogin
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('username')
                    ->label('Username')
                    ->required()
                    ->autofocus(),

                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
            ]);
    }

    public function authenticate(): ?LoginResponse
    {
        $response = parent::authenticate();

        $user = Auth::user();
        AuditLog::record('Login', $user, $user->full_name);

        return $response;
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.username' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        return [
            'username' => $data['username'],
            'password' => $data['password'],
        ];
    }

    protected function getRedirectUrl(): string
    {
        $user = Auth::user();

        if ($user?->role === 'admin') {
            return '/admin';
        }

        return '/teller';
    }
}
