<?php

namespace App\Filament\Admin\Resources\Customers\Pages;

use App\Enums\CustomerCategory;
use App\Filament\Admin\Resources\Customers\CustomerResource;
use App\Filament\Exports\CustomerExporter;
use App\Filament\Imports\CustomerImporter;
use App\Filament\Support\ImportModal;
use App\Filament\Widgets\CustomerStatsWidget;
use App\Models\AuditLog;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Alignment;
use Maatwebsite\Excel\Facades\Excel;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    public ?string $categoryFilter = null;
    public ?string $statusFilter = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(fn () => Excel::download(
                    new CustomerExporter($this->categoryFilter, $this->statusFilter),
                    'data-nasabah-' . now()->format('Y-m-d') . '.xlsx',
                )),

            ImportModal::make(
                importer: CustomerImporter::class,
                templateFilename: 'format-import-nasabah.csv',
                templateRows: fn () => [
                    ['Nama', 'Kategori'],
                    ...collect(CustomerCategory::cases())
                        ->map(fn (CustomerCategory $c, int $i) => ['Contoh Nasabah ' . ($i + 1), $c->label()])
                        ->all(),
                ],
                auditLabel: 'Import Nasabah',
                unit: 'nasabah',
            ),

            CreateAction::make()
                ->label('Tambah Nasabah')
                ->modalHeading('Tambah Nasabah')
                ->createAnother(false)
                ->modalSubmitActionLabel('Simpan')
                ->modalCancelActionLabel('Batalkan')
                ->modalWidth('sm')
                ->modalAlignment(Alignment::Start)
                ->modalFooterActionsAlignment(Alignment::End)
                ->stickyModalHeader()
                ->stickyModalFooter()
                ->after(fn ($record) => AuditLog::record('Tambah Nasabah', $record, $record->full_name)),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            CustomerStatsWidget::class,
        ];
    }
}
