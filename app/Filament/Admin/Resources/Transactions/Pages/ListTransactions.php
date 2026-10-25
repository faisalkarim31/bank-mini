<?php

namespace App\Filament\Admin\Resources\Transactions\Pages;

use App\Enums\TransactionType;
use App\Filament\Admin\Resources\Transactions\TransactionResource;
use App\Filament\Exports\TransactionExporter;
use App\Filament\Imports\TransactionImporter;
use App\Filament\Support\ImportModal;
use App\Filament\Widgets\TransactionStats;
use App\Models\AuditLog;
use App\Models\Customer;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class ListTransactions extends ListRecords
{
    protected static string $resource = TransactionResource::class;

    public ?string $typeFilter = null;
    public ?string $dateFrom = null;
    public ?string $dateUntil = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('filterTanggal')
                ->label(fn() => ($this->dateFrom || $this->dateUntil)
                    ? trim(($this->dateFrom ?? '...') . ' s/d ' . ($this->dateUntil ?? '...'))
                    : 'Semua Tanggal')
                ->icon('heroicon-o-calendar')
                ->color('gray')
                ->visible(fn () => Auth::user()?->role !== 'teller')
                ->modalHeading('Filter Tanggal')
                ->modalWidth('md')
                ->modalSubmitActionLabel('Terapkan')
                ->modalCancelActionLabel('Batalkan')
                ->fillForm(fn() => [
                    'from' => $this->dateFrom,
                    'until' => $this->dateUntil,
                ])
                ->schema([
                    DatePicker::make('from')->label('Dari Tanggal'),
                    DatePicker::make('until')->label('Sampai Tanggal'),
                ])
                ->extraModalFooterActions([
                    Action::make('resetTanggal')
                        ->label('Reset')
                        ->color('danger')
                        ->outlined()
                        ->visible(fn() => $this->dateFrom || $this->dateUntil)
                        ->action(function () {
                            $this->dateFrom = null;
                            $this->dateUntil = null;
                            $this->resetPage();
                        })
                        ->cancelParentActions(),
                ])
                ->action(function (array $data) {
                    $this->dateFrom = $data['from'] ?? null;
                    $this->dateUntil = $data['until'] ?? null;
                    $this->resetPage();
                }),

            Action::make('export')
                ->label('Export')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(fn() => Excel::download(
                    new TransactionExporter($this->typeFilter, $this->dateFrom, $this->dateUntil),
                    'transaksi-' . now()->format('Ymd-His') . '.xlsx',
                )),

            ImportModal::make(
                importer: TransactionImporter::class,
                templateFilename: 'format-import-transaksi.csv',
                templateRows: function () {
                    $name = Customer::query()->where('is_active', true)->value('full_name') ?? 'Nama Nasabah';
                    $processor = Auth::user()?->full_name ?? 'Nama Pegawai';
                    $day = now()->startOfDay();

                    return [
                        ['Tanggal Waktu', 'Nama Nasabah', 'Nominal', 'Jenis Transaksi', 'Diproses oleh'],
                        ...collect(TransactionType::cases())
                            ->map(fn (TransactionType $t, int $i) => [
                                $day->copy()->addHours(8 + $i)->format('Y-m-d H:i'),
                                $name,
                                $t->isCredit() ? 100000 : 25000,
                                $t->label(),
                                $processor,
                            ])
                            ->all(),
                    ];
                },
                auditLabel: 'Import Transaksi',
                unit: 'transaksi',
                afterImport: fn ($livewire) => $livewire->dispatch('transaction-saved'),
            ),

            CreateAction::make()
                ->label('Tambah Transaksi')
                ->modalHeading('Tambah Transaksi')
                ->createAnother(false)
                ->modalSubmitActionLabel('Simpan')
                ->modalCancelActionLabel('Batalkan')
                ->modalWidth('md')
                ->modalAlignment(Alignment::Start)
                ->modalFooterActionsAlignment(Alignment::End)
                ->stickyModalHeader()
                ->stickyModalFooter()
                ->after(function ($record, $livewire) {
                    $livewire->dispatch('transaction-saved');
                    AuditLog::record(
                        'Tambah Transaksi',
                        $record,
                        $record->transaction_code . ' · ' . ($record->customer?->full_name ?? '-'),
                    );
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            TransactionStats::class,
        ];
    }
}
