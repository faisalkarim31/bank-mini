@php
    $user = filament()->auth()->user();
    $role = \App\Filament\Admin\Resources\Users\UserResource::ROLES[$user->role] ?? $user->role;
    $isAdmin = strtolower((string) $user->role) === 'admin';
@endphp

<div class="me-3 hidden flex-col items-end gap-0.5 sm:flex">
    <span class="text-sm font-medium text-gray-950 dark:text-white">
        {{ $user->full_name }}
    </span>

    <x-filament::badge size="sm" :color="$isAdmin ? 'warning' : 'info'">
        {{ $role }}
    </x-filament::badge>
</div>
