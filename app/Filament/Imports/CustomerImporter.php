<?php

namespace App\Filament\Imports;

use App\Enums\CustomerCategory;
use App\Models\Customer;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class CustomerImporter implements ToCollection, WithHeadingRow
{
    public int $imported = 0;

    /** @var array<int, array{reason: string, detail: string}> */
    public array $failures = [];

    public function collection(Collection $rows): void
    {
        $categories = collect(CustomerCategory::cases())->mapWithKeys(fn (CustomerCategory $c) => [
            mb_strtolower($c->value) => $c,
            mb_strtolower($c->label()) => $c,
        ]);

        $existingNames = Customer::query()
            ->pluck('full_name')
            ->mapWithKeys(fn ($name) => [mb_strtolower(Str::squish((string) $name)) => true])
            ->all();

        foreach ($rows as $index => $row) {
            $line = $index + 2;

            $name = Str::squish((string) ($row['nama'] ?? $row['nama_lengkap'] ?? $row['full_name'] ?? ''));
            $categoryText = trim((string) ($row['kategori'] ?? $row['category'] ?? ''));

            if ($name === '' && $categoryText === '') {
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

            $category = $categories->get(mb_strtolower($categoryText));

            if (! $category) {
                $this->fail('Kategori tidak dikenali', "baris {$line} (\"{$categoryText}\")");
                continue;
            }

            $nameKey = mb_strtolower($name);

            if (isset($existingNames[$nameKey])) {
                $this->fail('Nama sudah terdaftar', $name);
                continue;
            }

            try {
                Customer::create([
                    'full_name' => $name,
                    'category' => $category,
                ]);

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
                ->body('File tidak berisi baris nasabah. Pastikan baris pertama berisi judul kolom "Nama" dan "Kategori".')
                ->warning()
                ->persistent();
        }

        // Semua berhasil
        if ($failed === 0) {
            return Notification::make()
                ->title('Import berhasil')
                ->body("{$this->imported} nasabah ditambahkan.")
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
                '<div>' . e("{$this->imported} nasabah berhasil ditambahkan.") . '</div>'
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
