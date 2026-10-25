<?php

namespace App\Filament\Admin\Resources\Customers\Pages;

use App\Filament\Admin\Resources\Customers\CustomerResource;
use App\Filament\Widgets\CustomerTransactionStatsWidget;
use App\Enums\TransactionType;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Schema;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    public ?string $dateFrom = null;
    public ?string $dateUntil = null;
    public ?string $typeFilter = null;

    public function getTitle(): string
    {
        return 'Detail Nasabah';
    }

    public function getBreadcrumbs(): array
    {
        return [
            CustomerResource::getUrl('index') => 'Kembali',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            // Action::make('back')
            //     ->label('Kembali')
            //     ->icon('heroicon-m-arrow-left')
            //     ->color('gray')
            //     ->url(CustomerResource::getUrl('index')),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                $this->getInfolistContentComponent(),

                Actions::make([
                $this->typeFilterDropdown(),

                Action::make('dateFilter')
                    ->label(fn () => ($this->dateFrom || $this->dateUntil) ? 'Tanggal Dipilih' : 'Semua Tanggal')
                    ->icon('heroicon-o-calendar')
                    ->color('gray')
                    ->form([
                        DatePicker::make('from')->label('Dari Tanggal')->default($this->dateFrom),
                        DatePicker::make('until')->label('Sampai Tanggal')->default($this->dateUntil),
                    ])
                    ->action(function (array $data) {
                        $this->dateFrom = $data['from'];
                        $this->dateUntil = $data['until'];
                        $this->dispatch('transaction-filters-updated', from: $this->dateFrom, until: $this->dateUntil, type: $this->typeFilter);
                    }),
            ])->alignEnd(),

                Livewire::make(CustomerTransactionStatsWidget::class, [
                    'record' => $this->getRecord(),
                ])->key('customer-transaction-stats'),

                $this->getRelationManagersContentComponent(),
            ]);
    }

    protected function typeFilterDropdown(): ActionGroup
    {
        $options = TransactionType::options();

        return ActionGroup::make(
            collect(['' => 'Semua Jenis Transaksi', ...$options])
                ->map(fn (string $label, string $value) => Action::make('type_' . ($value ?: 'all'))
                    ->label($label)
                    ->action(function () use ($value) {
                        $this->typeFilter = $value ?: null;
                        $this->dispatch('transaction-filters-updated', from: $this->dateFrom, until: $this->dateUntil, type: $this->typeFilter);
                    }))
                ->values()
                ->all()
        )
            ->button()
            ->label(fn () => $options[$this->typeFilter ?? ''] ?? 'Semua Jenis Transaksi')
            ->icon('heroicon-m-chevron-down')
            ->iconPosition('after')
            ->color('gray');
    }
}
