<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Filament\Admin\Resources\Users\UserResource;
use App\Filament\Admin\Resources\Users\Widgets\UserStatsWidget;
use App\Filament\Exports\UserExporter;
use App\Filament\Imports\UserImporter;
use App\Filament\Support\ImportModal;
use App\Models\AuditLog;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    public ?string $roleFilter = null;
    public ?string $statusFilter = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(fn () => Excel::download(
                    new UserExporter($this->roleFilter, $this->statusFilter),
                    'pegawai-' . now()->format('Ymd-His') . '.xlsx',
                )),

            ImportModal::make(
                importer: UserImporter::class,
                templateFilename: 'format-import-pegawai.csv',
                templateRows: fn () => [
                    ['Nama Lengkap', 'Peran', 'Status'],
                    ...collect(UserResource::ROLES)
                        ->values()
                        ->map(fn (string $label, int $i) => ['Contoh Pegawai ' . ($i + 1), $label, 'Aktif'])
                        ->all(),
                ],
                auditLabel: 'Import Pegawai',
                unit: 'pegawai',
                icon: 'heroicon-o-arrow-up-tray',
            ),

            CreateAction::make()
                ->label('Tambah Pengguna')
                ->modalHeading('Tambah Pengguna')
                ->modalWidth('md')
                ->createAnother(false)
                ->after(fn ($record) => AuditLog::record('Tambah Pegawai', $record, $record->full_name)),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            UserStatsWidget::class,
        ];
    }
}
