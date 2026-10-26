<?php

namespace App\Filament\Admin\Resources\AuditLogs\Pages;

use App\Filament\Admin\Resources\AuditLogs\AuditLogResource;
use App\Filament\Exports\AuditLogExporter;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Pages\ManageRecords;
use Livewire\Attributes\Url;
use Maatwebsite\Excel\Facades\Excel;

class ManageAuditLogs extends ManageRecords
{
    protected static string $resource = AuditLogResource::class;

    #[Url]
    public ?string $roleFilter = null;

    #[Url]
    public ?string $dateFrom = null;

    #[Url]
    public ?string $dateUntil = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('filterTanggal')
                ->label(fn () => ($this->dateFrom || $this->dateUntil)
                    ? trim(($this->dateFrom ?? '...') . ' s/d ' . ($this->dateUntil ?? '...'))
                    : 'Semua Tanggal')
                ->icon('heroicon-o-calendar')
                ->color('gray')
                ->modalHeading('Filter Tanggal')
                ->modalWidth('md')
                ->modalSubmitActionLabel('Terapkan')
                ->modalCancelActionLabel('Batalkan')
                ->fillForm(fn () => [
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
                        ->visible(fn () => $this->dateFrom || $this->dateUntil)
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
                ->action(fn () => Excel::download(
                    new AuditLogExporter(
                        $this->roleFilter,
                        $this->dateFrom,
                        $this->dateUntil,
                    ),
                    'riwayat-aktivitas-' . now()->format('Ymd-His') . '.xlsx',
                )),
        ];
    }
}
