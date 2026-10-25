<?php

namespace App\Filament\Imports;

use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class UserImporter implements ToCollection, WithHeadingRow
{
    private const STATUSES = [
        'aktif' => true,
        'ya' => true,
        'true' => true,
        '1' => true,
        'nonaktif' => false,
        'tidak aktif' => false,
        'tidak' => false,
        'false' => false,
        '0' => false,
    ];

    public int $imported = 0;

    /** @var array<int, array{reason: string, detail: string}> */
    public array $failures = [];

    public function collection(Collection $rows): void
    {
        $roles = [];

        foreach (UserResource::ROLES as $value => $label) {
            $roles[mb_strtolower($value)] = $value;
            $roles[mb_strtolower($label)] = $value;
        }

        $existingNames = User::query()
            ->pluck('full_name')
            ->mapWithKeys(fn ($name) => [mb_strtolower(Str::squish((string) $name)) => true])
            ->all();

        foreach ($rows as $index => $row) {
            $line = $index + 2;

            $name = Str::squish((string) ($row['nama_lengkap'] ?? $row['nama'] ?? $row['full_name'] ?? ''));
            $roleText = trim((string) ($row['peran'] ?? $row['role'] ?? ''));
            $statusRaw = $row['status'] ?? $row['is_active'] ?? null;

            if (is_bool($statusRaw)) {
                $statusText = $statusRaw ? '1' : '0';
            } else {
                $statusText = mb_strtolower(trim((string) $statusRaw));
            }

            if ($name === '' && $roleText === '' && $statusText === '') {
                continue;
            }

            if ($name === '') {
                $this->fail('Nama kosong', "baris {$line}");
                continue;
            }

            if (mb_strlen($name) > 255) {
                $this->fail('Nama terlalu panjang (maksimal 255 karakter)', "baris {$line}");
                continue;
            }

            $role = $roles[mb_strtolower($roleText)] ?? null;

            if (! $role) {
                $this->fail(
                    'Peran tidak dikenali',
                    "baris {$line}" . ($roleText !== '' ? " (\"{$roleText}\")" : '')
                );
                continue;
            }

            if ($statusText === '') {
                $active = true;
            } elseif (array_key_exists($statusText, self::STATUSES)) {
                $active = self::STATUSES[$statusText];
            } else {
                $this->fail('Status tidak dikenali', "baris {$line} (\"{$statusText}\")");
                continue;
            }

            $nameKey = mb_strtolower($name);

            if (isset($existingNames[$nameKey])) {
                $this->fail('Nama sudah terdaftar', $name);
                continue;
            }

            try {
                $user = new User();
                $user->full_name = $name;
                $user->role = $role;
                $user->is_active = $active;
                $user->save();

                $existingNames[$nameKey] = true;
                $this->imported++;
            } catch (\Throwable $e) {
                $this->fail('Gagal disimpan', "baris {$line}");
            }
        }
    }

    public function notification(): Notification
    {
        $failed = count($this->failures);

        // Tidak ada baris sama sekali
        if ($this->imported === 0 && $failed === 0) {
            return Notification::make()
                ->title('Tidak ada data yang diimpor')
                ->body('File tidak berisi baris pegawai. Pastikan baris pertama berisi judul kolom "Nama Lengkap" dan "Peran".')
                ->warning()
                ->persistent();
        }

        // Semua berhasil
        if ($failed === 0) {
            return Notification::make()
                ->title('Import berhasil')
                ->body("{$this->imported} pegawai ditambahkan.")
                ->success();
        }

        // Semua gagal: langsung tampilkan alasannya
        if ($this->imported === 0) {
            return Notification::make()
                ->title('Import gagal')
                ->body(new HtmlString($this->failureList()))
                ->danger()
                ->persistent();
        }

        // Sebagian berhasil
        return Notification::make()
            ->title('Import selesai sebagian')
            ->body(new HtmlString(
                '<div>' . e("{$this->imported} pegawai berhasil ditambahkan.") . '</div>'
                . '<div style="margin-top:.5rem">' . e("{$failed} baris dilewati:") . '</div>'
                . $this->failureList()
            ))
            ->warning()
            ->persistent();
    }

    private function fail(string $reason, string $detail): void
    {
        $this->failures[] = ['reason' => $reason, 'detail' => $detail];
    }

    private function failureList(): string
    {
        $html = '';

        foreach (collect($this->failures)->groupBy('reason') as $reason => $items) {
            $examples = $items->take(5)->map(fn ($item) => e($item['detail']))->implode(', ');
            $more = $items->count() > 5 ? ' dan ' . ($items->count() - 5) . ' lainnya' : '';

            $html .= '<div><b>' . e($reason) . ' (' . $items->count() . '):</b> ' . $examples . $more . '</div>';
        }

        return '<div style="margin-top:.25rem">' . $html . '</div>';
    }
}
