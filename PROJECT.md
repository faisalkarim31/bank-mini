# Bank Mini — Konteks Proyek

Dokumen ini ringkasan proyek supaya developer atau AI lain bisa langsung paham tanpa membaca semua file.
Disusun dari pembacaan kode dan hasil test otomatis. Bagian yang belum dibaca ditandai **[BELUM DIBACA]**.

- Terakhir diperbarui: 26 Oktober 2026
- Lokasi dev: `D:\Aplikasi\laragon\www\bank-mini` (Windows, Laragon, PowerShell)

---

## 1. Ringkasan

Aplikasi bank mini (kemungkinan lingkup sekolah, dilihat dari kategori nasabah: siswa, guru, staff, lainnya).
Mengelola nasabah, transaksi setor/tarik, saldo, dan riwayat aktivitas pegawai. Dua peran: **admin** dan **teller**, masing-masing punya panel Filament sendiri.

### Stack

| Komponen | Versi / Detail |
|---|---|
| PHP | 8.4.12 |
| Laravel | 13.32.0 |
| Filament | v5.8.4 (panel admin, tabel, aksi modal) |
| Livewire | v4.4.6 |
| Database | SQLite (dev), SQLite `:memory:` (test) |
| Excel | `maatwebsite/excel` (export) |
| Frontend | Vite, tema Filament per panel (`resources/css/filament/{admin,teller}/theme.css`) |
| Queue / Cache / Session | database |
| Timezone / Locale | Asia/Jakarta / en (UI berbahasa Indonesia) |

### Akun seed (`UserSeeder`)

| Username | Password | Role |
|---|---|---|
| `admin` | `admin123` | admin |
| `teller` | `teller123` | teller |

Setup lokal: `composer install` → `copy .env.example .env` → `php artisan key:generate` → `php artisan migrate --seed` → `npm install && npm run build` → `php artisan serve`.

### Data contoh (`DemoSeeder`, khusus dev)

Untuk cek manual di browser (terutama aturan teller "hanya hari ini"):

```
php artisan migrate:fresh --seed
php artisan db:seed --class=DemoSeeder
```

Isi: 14 nasabah (4 kategori, 2 nonaktif), sekitar 100 transaksi tersebar 1 sampai 45 hari lalu plus 9 transaksi hari ini, audit log login dan aksi. Saldo tidak pernah minus. Seeder tidak masuk `DatabaseSeeder` (jangan ikut ke produksi) dan menghitung saldo, kode transaksi, serta `created_by` sendiri lewat `DB::table`, karena `DatabaseSeeder` memakai `WithoutModelEvents` sehingga hook model mati saat seeding. Login `admin` melihat semua tanggal, `teller` hanya hari ini.

---

## 2. Role dan Hak Akses

| Fitur | Admin | Teller |
|---|:-:|:-:|
| Panel | `/admin` | `/teller` |
| Lihat daftar nasabah (search, filter kategori/status) | ✅ | ✅ |
| Lihat detail nasabah + riwayat transaksi | ✅ | ✅ |
| Tambah nasabah | ✅ | ✅ |
| Edit nasabah | ✅ | ✅ |
| Nonaktifkan / aktifkan nasabah | ✅ | ❌ |
| Lihat transaksi | ✅ semua tanggal | ✅ **hanya hari ini** |
| Filter tanggal transaksi | ✅ | ❌ (tombol disembunyikan) |
| Tambah transaksi | ✅ | ✅ |
| Edit transaksi | ✅ (hanya jika nasabah aktif) | ❌ |
| Riwayat aktivitas (audit log) | ✅ | ❌ |
| Kelola pegawai (Users) | ❌ **Dinonaktifkan** (`UserResource::canAccess()` = false, `/admin/users` selalu 403) | ❌ |
| Export (nasabah, transaksi, audit log) | ✅ | ✅ Export transaksi hanya hari ini (sudah diperbaiki) |
| Import (nasabah, transaksi) | ✅ | ✅ Import transaksi boleh tanggal lampau (**pertanyaan klien**, lihat bagian 8) |
| Dashboard: kartu angka | ✅ semua tanggal + filter | ✅ hanya hari ini |
| Dashboard: tabel terbaru, grafik kategori | ✅ semua tanggal + filter | ✅ hanya hari ini (sudah diperbaiki) |
| Dashboard: grafik tren | ✅ semua tanggal + filter | ❌ disembunyikan |
| Riwayat transaksi di detail nasabah | ✅ | ✅ semua tanggal (**pertanyaan klien**, lihat bagian 8) |
| Halaman "Tentang Bank" | ✅ | ✅ |

Penentu akses panel: `User::canAccessPanel()`.
- `is_active = false` → ditolak di semua panel.
- panel `auth` → semua user aktif.
- panel `admin` → `role === 'admin'`.
- panel `teller` → `role === 'teller'`.

Role disimpan sebagai kolom string `users.role` (`admin` | `teller`). Tidak memakai Spatie/Shield dan tidak ada Policy. Pembatasan fitur dilakukan lewat closure `->visible()` dan query di Resource.

---

## 3. Panel dan Route

Ada 3 panel Filament (`app/Providers/Filament/`):

| Panel | ID | Path | Fungsi |
|---|---|---|---|
| Auth | `auth` | `/` (default) | Halaman login bersama (`/login`, `/`) |
| Admin | `admin` | `/admin` | Panel admin, warna Orange |
| Teller | `teller` | `/teller` | Panel teller, warna Orange |

Route penting (`php artisan route:list --except-vendor`):

```
GET /                       login
GET /login                  login (panel auth)
GET /admin                  dashboard admin
GET /admin/login
GET /admin/customers        daftar nasabah
GET /admin/customers/{id}   detail nasabah
GET /admin/transactions     daftar transaksi
GET /admin/audit-logs       riwayat aktivitas
GET /admin/users            daftar pegawai
GET /admin/tentang-bank
GET /teller                 dashboard teller
GET /teller/customers       daftar nasabah (memakai Page milik Admin)
GET /teller/customers/{id}
GET /teller/transactions    daftar transaksi (memakai Page milik Admin)
GET /teller/tentang-bank
```

Redirect setelah login: `admin` → `/admin`, selain itu → `/teller` (`App\Filament\Auth\Login::getRedirectUrl`).
Pencarian global dimatikan di kedua panel (`globalSearch(false)`). Menu logout bawaan disembunyikan, diganti hook view `sidebar-logout`.

---

## 4. Model Data

### users
| Kolom | Tipe | Catatan |
|---|---|---|
| id | PK | |
| employee_id | string(20), unique, nullable | Auto: `P` + `yy` + 5 digit (contoh `P2600001`) |
| username | string, unique, nullable | Dipakai untuk login (bukan email) |
| password | string | cast `hashed` |
| full_name | string | |
| role | enum(`admin`,`teller`) | default `teller` |
| is_active | boolean | default true |
| created_by | unsignedBigInteger nullable | Auto = `Auth::id()` bila login |
| remember_token, timestamps | | |

### customers
| Kolom | Tipe | Catatan |
|---|---|---|
| id | PK | |
| account_number | string, unique | Auto: kode kategori (2 digit) + `yy` + 6 digit urut |
| full_name | string | |
| category | enum(`siswa`,`guru`,`staff`,`lainnya`) | cast ke `CustomerCategory` |
| balance | decimal(15,2) | default 0 |
| is_active | boolean | default true |
| created_by | FK → users.id | wajib |
| timestamps | | |

### transactions
| Kolom | Tipe | Catatan |
|---|---|---|
| id | PK | |
| customer_id | FK → customers.id | |
| transaction_code | string | Auto: `TRX` + `ymd` + 3 digit urut per hari (contoh `TRX261026001`) |
| type | enum(`setor_tunai`,`tarik_tunai`,`tarik_kas`) | cast ke `TransactionType` |
| amount | decimal(15,2) | |
| processed_by | **string** | Teks bebas nama pegawai, **bukan** FK ke users |
| timestamps | | |

### audit_logs
`id`, `user_id` (FK → users, **NOT NULL**), `action` (string), `target_type`, `target_id`, `target_name` (nullable), `timestamps`.

### Tabel bawaan Filament / Laravel
`sessions`, `notifications`, `imports`, `exports`, `failed_import_rows`, plus tabel cache/jobs.

### Relasi
```
User 1—* Customer         (created_by)
Customer 1—* Transaction  (customer_id)
Transaction *—1 User      (processor() memakai processed_by — TIDAK cocok, kolom bertipe string)
AuditLog *—1 User
```

### Enum
- `CustomerCategory`: Siswa(`01`), Guru(`02`), Staff(`03`, label `'Kelas'`), Lainnya(`04`). Method: `label()`, `color()`, `code()`, `options()`.
- `TransactionType`: SetorTunai (label Kredit), TarikTunai (label Debit), TarikKas (label Debit Kas). `isCredit()` hanya true untuk SetorTunai.

---

## 5. Aturan Bisnis

Hook model (`booted()`):

**Customer**
- `creating`: paksa `balance = 0`, `is_active = true`, `created_by = Auth::id()`. Nilai yang dikirim saat create **diabaikan**. Akibatnya create tanpa user login gagal (created_by NOT NULL).
- Nomor rekening dibuat otomatis kalau kosong.

**Transaction**
- `creating`: buat `transaction_code`.
- `created`: setor → `balance += amount`; tarik → `balance -= amount`.
- `updated`: bila `customer_id/type/amount` berubah, efek lama dibatalkan lalu efek baru diterapkan (dalam `DB::transaction`).
- Tidak ada hook delete dan tidak ada aksi delete di UI.
- `balanceIfChangedTo($type, $amount)`: hitung saldo andai transaksi diubah (dipakai untuk menolak edit yang bikin saldo minus).

**Validasi form transaksi (create)**
- Nasabah wajib aktif (`Rule::exists(...)->where('is_active', true)`).
- Nominal minimal 1000.
- Tarik (`tarik_tunai`, `tarik_kas`) tidak boleh melebihi saldo.
- `processed_by` wajib, maks 255 karakter.

**Edit transaksi**
- Tombol edit hanya muncul untuk admin dan nasabah aktif.
- `customer_id` tidak bisa diubah saat edit.
- Edit yang membuat saldo negatif ditolak (`$action->halt()` + notifikasi).

**Teller**
- Query transaksi dibatasi `whereDate('created_at', today())`.

**Audit log** (`AuditLog::record($action, $target, $targetName)`, dipanggil manual)
Aksi tercatat: `Login`, `Tambah Nasabah`, `Edit Nasabah`, `Nonaktifkan Nasabah`, `Aktifkan Nasabah`, `Tambah Transaksi`, `Edit Transaksi`, plus label import (`Import Nasabah`, `Import Transaksi`).

---

## 6. Alur Utama

**Login**
1. User buka `/login` (atau `/admin/login`, `/teller/login`), isi username + password.
2. Login berhasil → `AuditLog::record('Login')` → redirect sesuai role.
3. User nonaktif atau role tak cocok → akses panel ditolak (403).

**Tambah transaksi** (modal di `ListTransactions`)
1. Pilih jenis (toggle) → pilih nasabah aktif → isi nominal → isi nama pegawai.
2. Validasi (minimal 1000, saldo cukup bila tarik).
3. Simpan → hook `created` ubah saldo → audit log "Tambah Transaksi" → widget statistik refresh (`transaction-saved`).

**Edit transaksi** (admin)
1. Buka modal edit → ubah jenis/nominal/pegawai.
2. Cek `balanceIfChangedTo` ≥ 0, jika tidak → ditolak.
3. Simpan → hook `updated` koreksi saldo → audit log "Edit Transaksi".

**Nasabah**
1. Tambah/Edit lewat modal (`ListCustomers`).
2. Admin bisa Nonaktifkan/Aktifkan (dengan konfirmasi) → audit log.
3. Detail nasabah (`ViewCustomer`): infolist, widget statistik, relation manager riwayat transaksi dengan filter jenis dan tanggal.

**Filter dan search**
Filter bukan filter bawaan Filament, melainkan **properti publik Livewire**:
- `ListCustomers`: `categoryFilter`, `statusFilter`
- `ListTransactions`: `typeFilter`, `dateFrom`, `dateUntil`
- `ManageAuditLogs`: `roleFilter`, `dateFrom`, `dateUntil`
- `ViewCustomer`: `dateFrom`, `dateUntil`, `typeFilter` (dikirim ke relation manager lewat event `transaction-filters-updated`)

Kolom searchable: nasabah (`account_number`, `full_name`), transaksi (`customer.full_name`), audit log (`user.full_name`, `action`).

---

## 7. Struktur File (`app/`)

```
Enums/                       CustomerCategory, TransactionType
Models/                      AuditLog, Customer, Transaction, User
Providers/Filament/          AdminPanelProvider, AuthPanelProvider, TellerPanelProvider
Filament/
├─ Auth/Login.php            login kustom (username), audit log, redirect per role
├─ Admin/
│  ├─ Pages/                 Dashboard, AboutBank
│  └─ Resources/
│     ├─ Customers/          CustomerResource (form+tabel+infolist inline), Pages/{ListCustomers, ViewCustomer}, RelationManagers/TransactionsRelationManager
│     ├─ Transactions/       TransactionResource, Pages/ListTransactions
│     ├─ AuditLogs/          AuditLogResource, Pages/ManageAuditLogs
│     └─ Users/              UserResource (canAccess=false, fitur ditutup), Pages/ListUsers, Widgets/UserStatsWidget
├─ Teller/
│  ├─ Pages/                 Dashboard, AboutBank
│  └─ Resources/             CustomerResource, TransactionResource  (subclass kosong dari versi Admin)
├─ Widgets/                  CustomerStatsWidget, CustomerTransactionStatsWidget, LatestActivities (admin saja),
│                            LatestTransactions, StatsOverview (kartu Dashboard), TransactionCategoryChart,
│                            TransactionChart, TransactionStats (kartu halaman Transaksi), Concerns/AppliesDateFilter
├─ Exports/                  AuditLogExporter, CustomerExporter, TransactionExporter, UserExporter (maatwebsite/excel)
├─ Imports/                  CustomerImporter, TransactionImporter, UserImporter (ToCollection, WithHeadingRow)
└─ Support/                  HeadStyles (CSS tambahan), ImportModal (aksi import bersama: upload, unduh format, audit log)
```

**Catatan arsitektur**
- Teller **memakai ulang** Resource dan Page milik Admin. Resource teller hanyalah subclass kosong, perbedaan perilaku dikendalikan lewat cek `Auth::user()->role`.
- Resource Admin memakai pola "form/tabel/infolist langsung di Resource". File `Schemas/CustomerForm.php`, `Tables/CustomersTable.php`, `Schemas/TransactionForm.php`, `Tables/TransactionsTable.php` adalah scaffold bawaan Filament dan **tidak dipakai** (kode mati).
- `Pages/CreateCustomer`, `EditCustomer`, `CreateTransaction`, `EditTransaction` tidak terdaftar di `getPages()`, jadi juga kode mati. Create dan edit berjalan lewat aksi modal di halaman List.
- Belum ada `app/Policies` dan belum ada Form Request.
- `Dashboard` Teller memakai ulang `Dashboard` Admin. Daftar widget ditentukan per role di `Dashboard::getWidgets()` (teller: Transaksi Terbaru, Kategori, Aktivitas Terbaru yang tersaring `canView` admin saja). Urutan tampil mengikuti urutan array itu, bukan `$sort` widget.
- Widget memakai satu properti `$columnSpan` untuk semua role. Perubahan lebar untuk teller ikut kena ke admin (bug 8).
- Ada dua widget kartu dengan logika hampir sama: `StatsOverview` (Dashboard) dan `TransactionStats` (halaman Transaksi).
- Trait `AppliesDateFilter` versi awal hanya membaca filter tanggal dari halaman, tidak mengecek role (akar bug 4, sudah diperbaiki; versi terbaru belum dibaca ulang).
- Importer menyimpan "Diproses oleh" sebagai teks bebas. Nasabah baru dari import selalu saldo 0, kolom saldo di file diabaikan.
- Saldo awal nasabah dari data lama dimasukkan sebagai transaksi setor (Kredit), bukan lewat import nasabah.

---

## 8. Temuan dan Catatan Potensi Bug

### Bug terbuka (4 test merah)

| # | Bug | Lokasi | Test merah | Fix singkat |
|---|---|---|---|---|
| 6 | Export transaksi admin mengabaikan filter tanggal. Filter "Sampai" menghasilkan file kosong, filter "Dari" mengeluarkan semua tanggal. Regresi dari perbaikan bug 1 dan 2: `->when(! $isTeller && $this->dateFrom, fn ($q, $d) => ...)` membuat `$d` bernilai `true` (hasil `&&`), bukan tanggalnya. | `Exports/TransactionExporter::query()` | `ExporterQueryTest > export transaksi filter tanggal` | Pakai `fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom)` (dan `<=` untuk `dateUntil`). Hapus juga fallback `?? $transaction->processor` di `map()` |
| 7 | Kartu ringkasan di halaman Transaksi tidak ikut filter tabel (admin). `TransactionStats` tidak punya pendengar filter dan `ListTransactions` tidak mengirim event filter ke kartu. Di Dashboard dan detail nasabah kartu serupa sudah ikut filter. **Menunggu konfirmasi klien/desainer** (ikut filter atau tetap total dengan label periode). | `Widgets/TransactionStats`, `Transactions/Pages/ListTransactions` | `TransactionStatsTest` (filter tanggal, filter jenis) | Kirim event `transaction-filters-updated` dari halaman, widget mendengarkan dan menerapkan `dateFrom`, `dateUntil`, `type` seperti `CustomerTransactionStatsWidget` |
| 8 | Tabel "Transaksi Terbaru" di Dashboard admin tidak selebar penuh (2 kolom, ruang kosong di kanan). Regresi dari perubahan layout teller: `$columnSpan` `lg => 2` berlaku untuk semua role. | `Widgets/LatestTransactions` | `DashboardTest > admin tabel transaksi terbaru selebar penuh` | Lebar per role: admin `full`, teller 2 kolom, misal lewat `getColumnSpan()` (nama method perlu dicek di versi Filament yang dipakai) |

### Bug yang sudah diperbaiki (test hijau, sekarang berfungsi sebagai regresi)

| # | Bug | Test |
|---|---|---|
| 1 | Export transaksi: kolom "Diproses oleh" kosong (exporter membaca relasi `processor`, padahal `processed_by` teks bebas) | `ExporterQueryTest > kolom diproses oleh berisi nama pegawai` |
| 2 | Teller bisa export transaksi semua tanggal | `ExportActionTest > export transaksi teller hanya hari ini` |
| 3 | Filter peran Riwayat Aktivitas tidak tersimpan (`$roleFilter` tidak dideklarasikan) | `AuditLogTest::test_filter_peran` |
| 4 | Dashboard teller menampilkan data semua tanggal (tabel terbaru, grafik tren, grafik kategori). Perbaikan: grafik tren disembunyikan dari teller, sisanya dibatasi hari ini | `DashboardTest` (teller) |
| 5 | Teks placeholder "Isi peraturan kedua di sini." di Tentang Bank | `AboutBankTest` |

### Change request

| # | Usulan | Status |
|---|---|---|
| CR-1 | Hapus kartu "Saldo Hari Ini" (teller) di Dashboard dan halaman Transaksi. Isinya selisih Kredit − Debit − Debit Kas, bukan saldo, bisa minus dan menyesatkan. Kartu admin "Total Saldo" belum diputuskan (ikut dihapus atau tidak). Desain Figma perlu disesuaikan. | Menunggu persetujuan |

### Pertanyaan untuk klien

1. Teller boleh import transaksi? Sekarang bisa memasukkan transaksi tanggal lampau (importer hanya menolak tanggal masa depan).
2. Riwayat transaksi di detail nasabah untuk teller dibatasi hari ini atau semua tanggal? Sekarang semua tanggal, plus total kredit dan debit.
3. Nama kelas (X IPA dan sebagainya) dari data Excel klien perlu disimpan? Kategori nasabah hanya 4 jenis (siswa, guru, staff, lainnya), dan jadi kode nomor rekening.
4. Label kategori Staff tampil "Kelas". Sengaja atau typo?
5. Kartu ringkasan di halaman Transaksi (admin) ikut filter tabel, atau tetap total semua tanggal dengan label periode? (menentukan bentuk perbaikan bug 7)

### Catatan lain

| Temuan | Status |
|---|---|
| `processed_by` teks bebas, siapa pun bisa ketik nama sembarang. | Disengaja (permintaan klien). Relasi `processor()` sisa kode lama |
| Karakter `Â·` pada `target_name` audit log (`ListTransactions`) dan pesan import "Transaksi sudah ada" (`TransactionImporter`). Kemungkinan artefak encoding, file harus UTF-8. | Perlu cek |
| Model `Customer` mengabaikan `balance`, `is_active`, `created_by` saat create. Create tanpa user login gagal (created_by NOT NULL). | Disengaja, gotcha |
| `AuditLog.user_id` NOT NULL. `AuditLog::record()` tanpa user login akan error. | Gotcha |
| `CustomerStatsWidget`: grafik mini "Nasabah Aktif" dan "Nonaktif" memakai angka karangan (komentar `placeholder visual` di kode). | Ringan, menyesatkan |
| `UserImporter` membuat user tanpa password. Kemungkinan gagal semua jika kolom `password` NOT NULL. | Dugaan, fitur ditutup |
| `UserFactory::unverified()` menyetel `email_verified_at`, kolom tidak ada. | Minor, tidak dipakai |
| `Users/Widgets/UserStats.php` berisi class `UserStatsWidget` (nama file tidak sama), bikin "Cannot redeclare class" saat test boot panel berulang. File di-rename jadi `UserStatsWidget.php`. | Sudah diperbaiki lokal |
| `UserResource::canAccess()` selalu `false`. Fitur pegawai masih ada di kode tapi disembunyikan. | Disengaja: tidak dipakai klien |
| Import transaksi menolak transaksi identik (nasabah, jenis, nominal, menit sama) sebagai duplikat. Dua setoran sah di menit yang sama ikut terblokir. | Perilaku disengaja |
| Import transaksi sudah mengecek saldo sebelum tarik (aman dari saldo minus) dan memproses urut tanggal, bukan urutan baris. | Terverifikasi test |
| Halaman detail nasabah tidak punya tombol Export. | Terverifikasi dari kode |
| `TransactionChart` masih punya cabang untuk teller (tren per jam), padahal grafik ini tidak ditampilkan ke teller. | Kode mati, aman |
| Aplikasi diuji di SQLite, produksi MySQL. Bug 6 contohnya: filter "Dari" lolos di test karena SQLite membandingkan tanggal dengan `true` tanpa error, sedangkan di MySQL filter "Sampai" menghasilkan nol baris. Perilaku perbandingan bisa berbeda antar database | Gotcha, hati-hati pada kondisi `&&` di dalam `->when()` |

---

## 9. Testing

### Cara menjalankan

```
php artisan test                                   # semua
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php artisan test tests/Feature/Customers           # satu folder
php artisan test --filter=test_teller_hanya_lihat_transaksi_hari_ini
```

Konfigurasi `phpunit.xml`: SQLite `:memory:`, session/cache `array`, queue `sync`.
Framework: **PHPUnit** (class biasa). Pest belum terpasang.

### Struktur folder (pemisahan per domain)

```
tests/
├─ Support/
│  └─ BankTestCase.php          base class + helper (satu-satunya tempat helper)
├─ Unit/                        logika murni tanpa HTTP/Livewire
│  ├─ Enums/                    CustomerCategoryTest, TransactionTypeTest
│  ├─ Exports/                  ExporterQueryTest
│  └─ Models/                   TransactionBalanceTest
└─ Feature/                     alur lewat Livewire/HTTP, satu folder per domain
   ├─ Auth/                     PanelAccessTest, LoginTest
   ├─ AboutBank/                AboutBankTest
   ├─ AuditLogs/                AuditLogTest
   ├─ Customers/                CustomerTest, CustomerDetailTest
   ├─ Dashboard/                DashboardTest
   ├─ Exports/                  ExportActionTest
   ├─ Imports/                  CustomerImportTest, TransactionImportTest
   └─ Transactions/             TransactionTest, TransactionStatsTest
```

Aturan menambah test:
1. Folder mengikuti **domain**, bukan tipe teknis. Fitur baru → folder baru di `Feature/`.
2. Namespace mengikuti folder (`Tests\Feature\Customers`).
3. Class turunan `Tests\Support\BankTestCase` bila perlu database. Test enum murni boleh langsung `PHPUnit\Framework\TestCase`.
4. Nama method: `test_<perilaku_dalam_bahasa_indonesia>`. Satu method menguji satu perilaku.
5. Jangan menaruh helper di file test. Tambahkan ke `BankTestCase` (atau trait di `tests/Support/`).

### Helper di `BankTestCase`

| Helper | Fungsi |
|---|---|
| `actingAsRole($role, $panel = null, $attrs = [])` | Buat user, login, set panel Filament aktif |
| `makeCustomer($attrs)` | Buat nasabah. `balance` dan `is_active` diset lewat update setelah create (karena hook `creating` memaksa nilai default). Otomatis login admin kalau belum ada user login |
| `makeTransaction($customer, $type, $amount, $at)` | Buat transaksi, hook `created` ikut mengubah saldo. `$type` harus enum `TransactionType` (bukan string). `$at` (Carbon) untuk transaksi bertanggal lampau |
| `balanceOf($customer)` | Saldo terbaru sebagai float |

`setUp()` memanggil `withoutVite()` supaya test tidak butuh `npm run build`.

### Gotcha saat menulis test

- Panel harus di-set (`Filament::setCurrentPanel(...)`) sebelum `Livewire::test(...)`. Helper `actingAsRole` sudah melakukannya.
- Aksi modal diuji dengan `callAction('create', [...])` untuk header action, dan `callAction(TestAction::make('edit')->table($record), [...])` untuk aksi baris (`Filament\Actions\Testing\TestAction`).
- Filter diuji dengan `->set('categoryFilter', 'guru')`, bukan `filterTable()`.
- Hook `Transaction::created` mengubah saldo, jadi hitung saldo awal dengan memperhitungkan transaksi yang dibuat helper.
- Membandingkan saldo: pakai cast float (`balanceOf`). Kolom decimal bisa berupa string.
- Importer diuji langsung: `(new XImporter)->collection(collect([...baris sebagai array berkunci judul kolom...]))`, lalu cek `->imported` dan `->failures` (kunci `reason`). Perlu user login (`actingAsRole`) karena `Customer::create` butuh `created_by`.
- Widget statistik/chart diuji lewat `Livewire::test(Widget::class)->instance()` lalu memanggil method protected dengan closure: `(fn () => $this->getStats())->call($instance)` atau `getData()`. Label dan nilai kartu dibaca dari `$stat->getLabel()` dan `$stat->getValue()`.
- Widget tabel: `Livewire::test(LatestTransactions::class)->assertCanSeeTableRecords([...])`. Lebar widget dicek dari atribut `--col-span-lg` di HTML.
- Relation manager: `Livewire::test(TransactionsRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])`. Filter dikirim lewat `->call('applyFilters', $from, $until, $type)`.
- Kontrak filter widget detail nasabah: event `transaction-filters-updated` (`from`, `until`, `type`). Mengirim event ke widget yang tidak punya pendengar melempar `EventHandlerDoesNotExist`.
- Test berjalan di SQLite. Perilaku perbandingan tipe bisa beda dengan MySQL produksi (lihat bug 6), jadi cek manual di browser tetap perlu untuk filter tanggal.

### Inventaris test (118 test. 114 hijau, 4 merah: bug 6, 7, 8)

| File | Jumlah | Cakupan |
|---|:-:|---|
| `Feature/Auth/PanelAccessTest` | 7 | Guest redirect, admin/teller akses panel sendiri, halaman pegawai selalu 403, silang panel ditolak, user nonaktif ditolak |
| `Feature/Auth/LoginTest` | 3 | Login sukses + audit log, password salah, user nonaktif |
| `Feature/Customers/CustomerTest` | 11 | Lihat, search nama/rekening, filter kategori/status, tambah, nomor rekening auto, validasi, edit, toggle aktif, detail, teller tambah/edit, teller tidak bisa toggle |
| `Feature/Customers/CustomerDetailTest` | 9 | Halaman detail, riwayat hanya milik nasabah itu, filter jenis/tanggal/gabungan/reset, kartu statistik per jenis dan mengikuti filter |
| `Feature/Transactions/TransactionTest` | 18 | Tambah setor/tarik, saldo berubah, tarik > saldo ditolak (tunai dan kas), nominal minimum, field wajib, nasabah nonaktif ditolak, teller tambah, search, filter jenis/tanggal, teller hanya hari ini, edit hanya admin, edit nominal/jenis menyesuaikan saldo, edit bikin saldo minus ditolak |
| `Feature/Transactions/TransactionStatsTest` | 4 | Kartu ringkasan halaman Transaksi: tanpa filter, ikut filter tanggal, ikut filter jenis (**2 merah, bug 7**), teller tetap hari ini |
| `Feature/AuditLogs/AuditLogTest` | 4 | Lihat + search aksi, search nama pengguna, filter peran, filter tanggal |
| `Feature/Dashboard/DashboardTest` | 9 | Kartu statistik teller/admin, tabel terbaru/tren/kategori teller hanya hari ini, admin semua tanggal, lebar tabel terbaru admin (**1 merah, bug 8**) dan teller |
| `Feature/AboutBank/AboutBankTest` | 1 | Tidak ada teks placeholder di Peraturan |
| `Feature/Imports/CustomerImportTest` | 8 | Import berhasil, kategori nilai/label, saldo awal 0 + created_by, nama kembar, kategori tidak dikenal, nama kosong/panjang |
| `Feature/Imports/TransactionImportTest` | 17 | Saldo dan data dari file, jenis nilai/label, tarik > saldo ditolak, urut tanggal bukan urut baris, saldo tak pernah minus, nasabah tidak ada/nonaktif/kembar, tanggal masa depan/format/kosong, nominal rupiah/tidak valid, duplikat, baris kosong |
| `Feature/Exports/ExportActionTest` + `Unit/Exports/ExporterQueryTest` | 18 | Query dan kolom exporter, aksi export (download), teller hanya hari ini, filter tanggal (**1 merah, bug 6**) |
| `Unit/Enums/CustomerCategoryTest` | 3 | Kode kategori, kode unik, options |
| `Unit/Enums/TransactionTypeTest` | 2 | `isCredit`, options |
| `Unit/Models/TransactionBalanceTest` | 4 | `balanceIfChangedTo`, saldo otomatis, kode transaksi harian |

### Roadmap test (bertahap)

| Fase | Isi | Status |
|---|---|---|
| 1 | Struktur test, base class, akses panel, login | ✅ Hijau |
| 2 | Nasabah (CRUD, search, filter, role) | ✅ Hijau |
| 3 | Transaksi (create, validasi, saldo, edit, role) | ✅ Hijau |
| 4 | Audit log (lihat, search, filter) | ✅ Hijau |
| 5 | Jalankan semua dan perbaiki yang merah | ✅ 114 dari 118 hijau, 4 merah = bug terbuka 6, 7, 8 |
| 6 | Users resource (kelola pegawai) | ⏸ Disembunyikan (tidak dipakai klien). Test hanya memastikan 403 |
| 7 | Export (nasabah, transaksi, audit log) | ✅ Selesai |
| 8 | Import (nasabah, transaksi) | ✅ Hijau |
| 9 | Widget dashboard, detail nasabah, Tentang Bank, kartu halaman Transaksi | ✅ Selesai (bug 4, 5 diperbaiki, bug 7, 8 terbuka) |
| 10 | Regresi untuk temuan di bagian 8 | ⏳ Test merah 6, 7, 8 jadi bukti. Hijau semua setelah dev memperbaiki |

Setelah bug 6, 7, 8 diperbaiki, jalankan `php artisan test` sekali lagi. Targetnya 118 hijau.

---

## 10. Belum Dibaca (konteks yang masih kosong)

`Exports/CustomerExporter`, `UserExporter`, `AuditLogExporter` (sebagian), versi terbaru trait `Widgets/Concerns/AppliesDateFilter` (setelah perbaikan bug 4), `window.exportDashboardPdf` (JS export PDF Dashboard, dugaan ikut membawa data bila widget bocor, perlu dicek manual di teller), view hook `resources/views/filament/admin/hooks/*`, `routes/web.php`, `composer.json`, `Support/HeadStyles` (hanya CSS).
Saat bagian-bagian ini dibaca, perbarui tabel hak akses (bagian 2), struktur (bagian 7), dan roadmap (bagian 9).