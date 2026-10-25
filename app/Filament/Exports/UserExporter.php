<?php

namespace App\Filament\Exports;

use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UserExporter implements
    FromQuery,
    WithHeadings,
    WithMapping,
    WithStyles,
    WithColumnWidths
{
    public function __construct(
        protected ?string $role = null,
        protected ?string $status = null,
    ) {}

    public function query(): Builder
    {
        return User::query()
            ->when($this->role, fn ($q, $r) => $q->where('role', $r))
            ->when($this->status, fn ($q, $s) => $q->where('is_active', $s === 'aktif'))
            ->orderBy('full_name');
    }

    public function headings(): array
    {
        return ['ID Pegawai', 'Nama Lengkap', 'Peran', 'Status'];
    }

    public function map($user): array
    {
        return [
            $user->employee_id,
            $user->full_name,
            UserResource::ROLES[$user->role] ?? $user->role,
            $user->is_active ? 'Aktif' : 'Nonaktif',
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
            'A' => 18, // ID Pegawai
            'B' => 25, // Nama Lengkap
            'C' => 15, // Peran
            'D' => 15, // Status
        ];
    }
}
