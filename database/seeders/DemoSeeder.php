<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Data contoh untuk cek manual (khusus dev, jangan dijalankan di produksi).
 *
 * Jalankan:
 *   php artisan migrate:fresh --seed
 *   php artisan db:seed --class=DemoSeeder
 *
 * Tidak memakai hook model (saldo, kode transaksi, created_by), semua dihitung
 * di sini lewat DB::table, jadi hasilnya sama walau events dimatikan.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('customers')->exists()) {
            $this->command?->warn('Tabel customers sudah berisi. Jalankan "php artisan migrate:fresh --seed" dulu.');

            return;
        }

        $admin = User::where('username', 'admin')->first();
        $teller = User::where('username', 'teller')->first();

        if (! $admin || ! $teller) {
            $this->command?->warn('User admin/teller belum ada. Jalankan UserSeeder dulu (migrate --seed).');

            return;
        }

        mt_srand(42); // data sama setiap kali dijalankan

        $now = now();
        $yy = $now->format('y');
        $maxMinToday = max(1, $now->hour * 60 + $now->minute);
        $categoryCode = ['siswa' => '01', 'guru' => '02', 'staff' => '03', 'lainnya' => '04'];

        // [nama, kategori, terdaftar berapa hari lalu, aktif]
        $seed = [
            ['Ahmad Fauzi', 'siswa', 55, true],
            ['Siti Nurhaliza', 'siswa', 52, true],
            ['Rizky Pratama', 'siswa', 48, true],
            ['Putri Maharani', 'siswa', 40, true],
            ['Bagas Setiawan', 'siswa', 35, false],
            ['Dewi Anggraini', 'siswa', 30, true],
            ['Ratna Sari, S.Pd', 'guru', 54, true],
            ['Hendra Wijaya, M.Pd', 'guru', 50, true],
            ['Maya Kusuma, S.Pd', 'guru', 33, true],
            ['Joko Susilo', 'staff', 45, false],
            ['Linda Permata', 'staff', 38, true],
            ['Slamet Riyadi', 'staff', 20, true],
            ['Bu Heni (Wali Murid)', 'lainnya', 25, true],
            ['Koperasi Sekolah', 'lainnya', 15, true],
        ];

        $sequence = [];
        $customers = [];

        foreach ($seed as [$name, $category, $daysAgo, $active]) {
            $key = $categoryCode[$category].$yy;
            $sequence[$key] = ($sequence[$key] ?? 0) + 1;
            $createdAt = $now->copy()->subDays($daysAgo)->setTime(8, 0);
            $creator = $sequence[$key] % 3 === 0 ? $teller : $admin;

            $customers[] = [
                'id' => DB::table('customers')->insertGetId([
                    'account_number' => $key.str_pad($sequence[$key], 6, '0', STR_PAD_LEFT),
                    'full_name' => $name,
                    'category' => $category,
                    'balance' => 0,
                    'is_active' => true,
                    'created_by' => $creator->id,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]),
                'name' => $name,
                'active' => $active,
                'created_at' => $createdAt,
                'by' => $creator,
            ];
        }

        $amounts = [5000, 10000, 20000, 25000, 50000, 75000, 100000, 150000, 200000, 500000];
        $names = ['Teller', 'Teller', 'Admin', 'Rina Wulandari', 'Budi Hartono', 'Sari Oktaviani'];
        $pick = fn (array $list) => $list[mt_rand(0, count($list) - 1)];

        // ---------- Kumpulkan event transaksi ----------
        $events = [];

        // Setoran awal tiap nasabah
        foreach ($customers as $i => $c) {
            $events[] = [
                'at' => $c['created_at']->copy()->addHours(2),
                'c' => $i,
                'type' => 'setor_tunai',
                'amount' => $pick([50000, 100000, 200000, 500000]),
            ];
        }

        // Transaksi acak 1-45 hari lalu
        for ($n = 0; $n < 90; $n++) {
            $at = $now->copy()->subDays(mt_rand(1, 45))->startOfDay()->addMinutes(mt_rand(7 * 60, 15 * 60));
            $i = mt_rand(0, count($customers) - 1);

            if ($at->lt($customers[$i]['created_at']->copy()->addHours(3))) {
                continue;
            }

            $r = mt_rand(1, 100);
            $events[] = [
                'at' => $at,
                'c' => $i,
                'type' => $r <= 55 ? 'setor_tunai' : ($r <= 85 ? 'tarik_tunai' : 'tarik_kas'),
                'amount' => $pick($amounts),
            ];
        }

        // Transaksi hari ini (hanya nasabah aktif), ini yang harus muncul di export teller
        $activeIdx = array_keys(array_filter($customers, fn ($c) => $c['active']));
        for ($n = 0; $n < 9; $n++) {
            $r = mt_rand(1, 100);
            $events[] = [
                'at' => $now->copy()->startOfDay()->addMinutes(mt_rand(0, $maxMinToday)),
                'c' => $pick($activeIdx),
                'type' => $r <= 50 ? 'setor_tunai' : ($r <= 80 ? 'tarik_tunai' : 'tarik_kas'),
                'amount' => $pick($amounts),
            ];
        }

        usort($events, fn ($a, $b) => $a['at']->timestamp <=> $b['at']->timestamp);

        // ---------- Proses berurutan, saldo tidak boleh minus ----------
        $balance = array_fill(0, count($customers), 0);
        $perDay = [];
        $audit = [];

        foreach ($customers as $c) {
            $audit[] = [$c['created_at'], $c['by']->id, 'Tambah Nasabah', 'App\\Models\\Customer', $c['id'], $c['name']];
        }

        foreach ($events as $e) {
            $i = $e['c'];
            $type = $e['type'];
            $amount = $e['amount'];

            if ($type !== 'setor_tunai') {
                if ($balance[$i] < 1000) {
                    $type = 'setor_tunai';
                } else {
                    $amount = max(1000, min($amount, (int) (floor($balance[$i] * 0.6 / 1000) * 1000)));
                }
            }

            $balance[$i] += $type === 'setor_tunai' ? $amount : -$amount;

            $day = $e['at']->format('ymd');
            $perDay[$day] = ($perDay[$day] ?? 0) + 1;
            $code = 'TRX'.$day.str_pad($perDay[$day], 3, '0', STR_PAD_LEFT);
            $by = $pick($names);

            $trxId = DB::table('transactions')->insertGetId([
                'customer_id' => $customers[$i]['id'],
                'transaction_code' => $code,
                'type' => $type,
                'amount' => $amount,
                'processed_by' => $by,
                'created_at' => $e['at'],
                'updated_at' => $e['at'],
            ]);

            $audit[] = [
                $e['at'],
                $by === 'Admin' ? $admin->id : $teller->id,
                'Tambah Transaksi',
                'App\\Models\\Transaction',
                $trxId,
                $code.' - '.$customers[$i]['name'],
            ];
        }

        // ---------- Saldo akhir + status nonaktif ----------
        foreach ($customers as $i => $c) {
            DB::table('customers')->where('id', $c['id'])->update([
                'balance' => $balance[$i],
                'is_active' => $c['active'],
            ]);

            if (! $c['active']) {
                $audit[] = [
                    $now->copy()->subDays(2)->setTime(10, 0),
                    $admin->id,
                    'Nonaktifkan Nasabah',
                    'App\\Models\\Customer',
                    $c['id'],
                    $c['name'],
                ];
            }
        }

        // ---------- Login 20 hari terakhir ----------
        for ($d = 0; $d <= 20; $d++) {
            $day = $now->copy()->subDays($d)->startOfDay();
            foreach ([$admin, $teller] as $u) {
                $minutes = $d === 0 ? mt_rand(0, $maxMinToday) : mt_rand(7 * 60, 9 * 60);
                $audit[] = [$day->copy()->addMinutes($minutes), $u->id, 'Login', null, null, null];
            }
        }

        usort($audit, fn ($a, $b) => $a[0]->timestamp <=> $b[0]->timestamp);

        foreach ($audit as [$at, $userId, $action, $type, $targetId, $targetName]) {
            DB::table('audit_logs')->insert([
                'user_id' => $userId,
                'action' => $action,
                'target_type' => $type,
                'target_id' => $targetId,
                'target_name' => $targetName,
                'created_at' => $at,
                'updated_at' => $at,
            ]);
        }

        $today = DB::table('transactions')->whereDate('created_at', today())->count();

        $this->command?->info(sprintf(
            'Demo: %d nasabah, %d transaksi (%d hari ini), %d audit log.',
            count($customers),
            count($events),
            $today,
            count($audit),
        ));
    }
}