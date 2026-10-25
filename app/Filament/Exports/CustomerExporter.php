<?php

namespace App\Filament\Exports;

use App\Enums\CustomerCategory;
use App\Models\Customer;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Database\Eloquent\Builder;

class CustomerExporter extends DefaultValueBinder implements
    FromQuery,
    WithHeadings,
    WithMapping,
    WithStyles,
    WithColumnWidths,
    WithCustomValueBinder
{
    public function __construct(
        protected ?string $category = null,
        protected ?string $status = null,
    ) {}


    public function query(): Builder
    {
        return Customer::query()
            ->when($this->category, fn ($q, $c) => $q->where('category', $c))
            ->when($this->status, fn ($q, $s) => $q->where('is_active', $s === 'aktif'));
    }

    public function headings(): array
    {
        return ['No. Rekening', 'Nama', 'Kategori', 'Saldo', 'Status', 'Terdaftar'];
    }

    public function map($customer): array
    {
        return [
            $customer->account_number,
            $customer->full_name,
            $customer->category instanceof CustomerCategory
                ? $customer->category->label()
                : $customer->category,
            (int) $customer->balance,
            $customer->is_active ? 'Aktif' : 'Nonaktif',
            $customer->created_at?->format('d M Y, H:i'),
        ];
    }

    public function bindValue(Cell $cell, $value): bool
    {
        if ($cell->getColumn() === 'A') {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'EA580C'],
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 18, // No. Rekening
            'B' => 28, // Nama
            'C' => 14, // Kategori
            'D' => 15, // Saldo
            'E' => 12, // Status
            'F' => 22, // Terdaftar
        ];
    }
}
