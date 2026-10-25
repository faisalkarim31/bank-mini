<?php

namespace App\Filament\Exports;

use App\Enums\TransactionType;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
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
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class TransactionExporter extends DefaultValueBinder implements
    FromQuery,
    WithHeadings,
    WithMapping,
    WithStyles,
    WithColumnWidths,
    WithCustomValueBinder
{
    public function __construct(
        protected ?string $type = null,
        protected ?string $dateFrom = null,
        protected ?string $dateUntil = null,
    ) {}

    public function query(): Builder
    {
        return Transaction::query()
            ->with(['customer', 'processor'])
            ->when($this->type, fn ($q, $t) => $q->where('type', $t))
            ->when($this->dateFrom, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($this->dateUntil, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest();
    }

    public function headings(): array
    {
        return [
            'ID Transaksi',
            'Waktu',
            'Nasabah',
            'No. Rekening',
            'Jenis Transaksi',
            'Nominal',
            'Diproses oleh',
        ];
    }

    public function map($transaction): array
    {
        return [
            $transaction->transaction_code,
            $transaction->created_at?->format('d M Y, H:i'),
            $transaction->customer?->full_name,
            $transaction->customer?->account_number,
            $transaction->type instanceof TransactionType
                ? $transaction->type->label()
                : $transaction->type,
            $transaction->amount,
            $transaction->processor?->full_name,
        ];
    }

    public function bindValue(Cell $cell, $value): bool
    {
        if ($cell->getColumn() === 'D') {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            'G' => [
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ],

            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'EA580C'],
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 18, // ID Transaksi
            'B' => 22, // Waktu
            'C' => 25, // Nasabah
            'D' => 18, // No. Rekening
            'E' => 18, // Jenis Transaksi
            'F' => 15, // Nominal
            'G' => 18, // Diproses oleh
        ];
    }
}
