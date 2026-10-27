<?php

namespace App\Filament\Exports;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TransactionExporter extends DefaultValueBinder implements
    FromQuery,
    WithHeadings,
    WithMapping,
    WithStyles,
    WithColumnWidths,
    WithCustomValueBinder
{
    public function __construct(
        protected Builder $tableQuery,
    ) {}

    public function query(): Builder
    {
        $query = clone $this->tableQuery;

        if (Auth::user()?->role === 'teller') {
            $query->whereDate('created_at', today()->toDateString());
        }

        return $query->with('customer');
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
            $transaction->processed_by,
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
            'A' => 18,
            'B' => 22,
            'C' => 25,
            'D' => 18,
            'E' => 18,
            'F' => 15,
            'G' => 18,
        ];
    }
}
