<?php

namespace App\Filament\Imports;

use App\Enums\TransactionType;
use App\Models\Customer;
use App\Models\Transaction;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class TransactionImporter implements ToCollection, WithHeadingRow
{
    private const DATE_FORMATS = [
        'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i', 'Y-m-d',
        'd/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y',
        'd-m-Y H:i:s', 'd-m-Y H:i', 'd-m-Y',
        'd M Y, H:i', 'd M Y H:i', 'd M Y',
        'd F Y, H:i', 'd F Y H:i', 'd F Y',
    ];

    private const MONTHS = [
        'januari' => 'Jan', 'februari' => 'Feb', 'maret' => 'Mar', 'april' => 'Apr',
        'mei' => 'May', 'juni' => 'Jun', 'juli' => 'Jul', 'agustus' => 'Aug',
        'september' => 'Sep', 'oktober' => 'Oct', 'november' => 'Nov', 'desember' => 'Dec',
        'peb' => 'Feb', 'agu' => 'Aug', 'ags' => 'Aug', 'okt' => 'Oct', 'nop' => 'Nov', 'des' => 'Dec',
    ];

    public int $imported = 0;

    /** @var array<int, array{reason: string, detail: string}> */
    public array $failures = [];

    public function collection(Collection $rows): void
    {
        $types = collect(TransactionType::cases())->mapWithKeys(fn (TransactionType $t) => [
            mb_strtolower($t->value) => $t,
            mb_strtolower($t->label()) => $t,
        ]);

        $customers = Customer::all()->groupBy(fn (Customer $c) => $this->nameKey((string) $c->full_name));

        $prepared = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;

            $dateRaw = $row['tanggal_waktu'] ?? $row['tanggalwaktu'] ?? $row['tanggal_dan_waktu'] ?? $row['waktu'] ?? $row['tanggal'] ?? null;
            $name = Str::squish((string) ($row['nama_nasabah'] ?? $row['nasabah'] ?? $row['nama'] ?? ''));
            $typeText = trim((string) ($row['jenis_transaksi'] ?? $row['jenis'] ?? $row['type'] ?? ''));
            $amountRaw = $row['nominal'] ?? $row['jumlah'] ?? $row['amount'] ?? null;
            $processorName = Str::squish((string) ($row['diproses_oleh'] ?? $row['processor'] ?? ''));

            if (blank($dateRaw) && $name === '' && $typeText === '' && blank($amountRaw) && $processorName === '') {
                continue;
            }

            if (blank($dateRaw)) {
                $this->fail('Tanggal kosong', "baris {$line}");
                continue;
            }

            $date = $this->parseDate($dateRaw);

            if (! $date) {
                $shown = is_scalar($dateRaw) ? Str::limit((string) $dateRaw, 30) : '';
                $this->fail('Format tanggal tidak dikenali', "baris {$line}" . ($shown !== '' ? " (\"{$shown}\")" : ''));
                continue;
            }

            if ($date->gt(now())) {
                $this->fail('Tanggal di masa depan', "baris {$line}");
                continue;
            }

            if ($name === '') {
                $this->fail('Nama nasabah kosong', "baris {$line}");
                continue;
            }

            $matches = $customers->get($this->nameKey($name));

            if (! $matches) {
                $this->fail('Nasabah tidak ditemukan', $name);
                continue;
            }

            $active = $matches->where('is_active', true);

            if ($active->isEmpty()) {
                $this->fail('Nasabah nonaktif', $name);
                continue;
            }

            if ($active->count() > 1) {
                $this->fail('Nama nasabah kembar', $name . ' (' . $active->count() . ' nasabah)');
                continue;
            }

            $customer = $active->first();

            $type = $types->get(mb_strtolower($typeText));

            if (! $type) {
                $this->fail(
                    'Jenis transaksi tidak dikenali',
                    "baris {$line}" . ($typeText !== '' ? " (\"{$typeText}\")" : '')
                );
                continue;
            }

            $amount = $this->parseAmount($amountRaw);

            if ($amount === null || floor($amount) != $amount) {
                $this->fail('Nominal tidak valid', "baris {$line}");
                continue;
            }

            if ($amount < 1000) {
                $this->fail('Nominal di bawah Rp 1.000', "baris {$line}");
                continue;
            }

            if ($processorName === '') {
                $this->fail('Diproses oleh kosong', "baris {$line}");
                continue;
            }

            if (mb_strlen($processorName) > 255) {
                $this->fail('Nama pemroses terlalu panjang (maksimal 255 karakter)', "baris {$line}");
                continue;
            }

            $prepared[] = [
                'line' => $line,
                'date' => $date,
                'customer' => $customer,
                'type' => $type,
                'amount' => (int) $amount,
                'processor' => $processorName,
            ];
        }

        $sorted = collect($prepared)->sortBy(fn (array $item) => $item['date']->getTimestamp())->values();

        foreach ($sorted as $item) {
            $customer = $item['customer'];

            $duplicate = Transaction::query()
                ->where('customer_id', $customer->id)
                ->where('type', $item['type']->value)
                ->where('amount', $item['amount'])
                ->where('created_at', $item['date']->format('Y-m-d H:i:s'))
                ->exists();

            if ($duplicate) {
                $this->fail('Transaksi sudah ada', $customer->full_name . ' · ' . $item['date']->format('d/m/Y H:i'));
                continue;
            }

            if (! $item['type']->isCredit()) {
                $customer->refresh();

                if ($item['amount'] > $customer->balance) {
                    $this->fail(
                        'Saldo tidak mencukupi',
                        "{$customer->full_name} (saldo Rp " . number_format($customer->balance, 0, ',', '.') . ')'
                    );
                    continue;
                }
            }

            try {
                $transaction = new Transaction([
                    'customer_id' => $customer->id,
                    'type' => $item['type'],
                    'amount' => $item['amount'],
                    'processed_by' => $item['processor'],
                ]);

                $transaction->created_at = $item['date'];
                $transaction->save();

                $this->imported++;
            } catch (\Throwable $e) {
                $this->fail('Gagal disimpan', "baris {$item['line']}");
            }
        }
    }

    public function notification(): Notification
    {
        $failed = count($this->failures);

        if ($this->imported === 0 && $failed === 0) {
            return Notification::make()
                ->title('Tidak ada data yang diimpor')
                ->body('File tidak berisi baris transaksi. Pastikan baris pertama berisi judul kolom "Tanggal Waktu", "Nama Nasabah", "Nominal", "Jenis Transaksi", dan "Diproses oleh".')
                ->warning()
                ->persistent();
        }

        if ($failed === 0) {
            return Notification::make()
                ->title('Import berhasil')
                ->body("{$this->imported} transaksi ditambahkan.")
                ->success();
        }

        if ($this->imported === 0) {
            return Notification::make()
                ->title('Import gagal')
                ->body(new HtmlString($this->failureList()))
                ->danger()
                ->persistent();
        }

        return Notification::make()
            ->title('Import selesai sebagian')
            ->body(new HtmlString(
                '<div>' . e("{$this->imported} transaksi berhasil ditambahkan.") . '</div>'
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

    private function nameKey(string $name): string
    {
        return mb_strtolower(Str::squish($name));
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric(trim($value)))) {
            $serial = (float) $value;

            if ($serial < 20000 || $serial > 80000) {
                return null;
            }

            $date = ExcelDate::excelToDateTimeObject($serial);

            return Carbon::parse($date->format('Y-m-d H:i:s'), config('app.timezone'));
        }

        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        $text = preg_replace_callback(
            '/\b[[:alpha:]]+\b/u',
            fn (array $m) => self::MONTHS[mb_strtolower($m[0])] ?? $m[0],
            $text
        );

        foreach (self::DATE_FORMATS as $format) {
            $date = \DateTimeImmutable::createFromFormat('!' . $format, $text);
            $errors = \DateTimeImmutable::getLastErrors();

            if ($date && (! $errors || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return Carbon::instance($date);
            }
        }

        return null;
    }

    private function parseAmount(mixed $value): ?float
    {
        if (is_string($value)) {
            $text = trim(preg_replace('/^rp\.?\s*/i', '', trim($value)));

            if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $text)) {
                $text = str_replace(['.', ','], ['', '.'], $text);
            }

            $value = $text;
        }

        return is_numeric($value) ? (float) $value : null;
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
