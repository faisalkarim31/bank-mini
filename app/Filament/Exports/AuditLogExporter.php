<?php

namespace App\Filament\Exports;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AuditLogExporter implements
    FromQuery,
    WithHeadings,
    WithMapping,
    WithStyles,
    WithColumnWidths
{
    public function __construct(
        protected ?string $role = null,
        protected ?string $dateFrom = null,
        protected ?string $dateUntil = null,
    ) {}

    public function query(): Builder
    {
        return AuditLog::query()
            ->with('user')
            ->when($this->role, fn ($q, $r) => $q->whereHas('user', fn ($u) => $u->where('role', $r)))
            ->when($this->dateFrom, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($this->dateUntil, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest();
    }

    public function headings(): array
    {
        return ['Waktu', 'Pengguna', 'Peran', 'Aksi', 'Target'];
    }

    public function map($log): array
    {
        return [
            $log->created_at?->format('d M Y, H:i'),
            $log->user?->full_name,
            match ($log->user?->role) {
                'admin' => 'Admin',
                'teller' => 'Teller',
                default => '-',
            },
            $log->action,
            $log->target_name ?? '-',
        ];
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
            'A' => 22, // Waktu
            'B' => 25, // Pengguna
            'C' => 15, // Peran
            'D' => 25, // Aksi
            'E' => 30, // Target
        ];
    }
}
